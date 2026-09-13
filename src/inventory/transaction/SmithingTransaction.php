<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\inventory\transaction;

use pocketmine\crafting\SmithingRecipe;
use pocketmine\item\Item;
use pocketmine\player\Player;
use function count;

class SmithingTransaction extends InventoryTransaction{

	public function __construct(
		Player $source,
		private readonly SmithingRecipe $recipe,
		private readonly Item $template,
		private readonly Item $input,
		private readonly Item $addition
	){
		parent::__construct($source);
	}

	public function getRecipe() : SmithingRecipe{ return $this->recipe; }

	public function getTemplate() : Item{ return clone $this->template; }

	public function getInput() : Item{ return clone $this->input; }

	public function getAddition() : Item{ return clone $this->addition; }

	/**
	 * @param Item[] $consumedItems
	 * @phpstan-param list<Item> $consumedItems
	 *
	 * @throws TransactionValidationException
	 */
	private function validateConsumedItems(array $consumedItems) : void{
		$expectedItems = [$this->template, $this->input, $this->addition];
		foreach($consumedItems as $consumedItem){
			if($consumedItem->getCount() !== 1){
				throw new TransactionValidationException("Expected exactly 1 " . $consumedItem->getName() . " to be consumed, got " . $consumedItem->getCount());
			}
			foreach($expectedItems as $key => $expectedItem){
				if($consumedItem->canStackWith($expectedItem)){
					unset($expectedItems[$key]);
					continue 2;
				}
			}

			throw new TransactionValidationException("Item " . $consumedItem->getName() . " is not in the smithing table");
		}
	}

	public function validate() : void{
		if(count($this->actions) < 1){
			throw new TransactionValidationException("Transaction must have at least one action to be executable");
		}

		$createdItems = [];
		$consumedItems = [];
		//matchItems() can't be used here - the result may stack with one of the inputs, e.g. re-applying the trim an
		//armour piece already has
		$this->separateCreatedAndConsumedItems($createdItems, $consumedItems);

		if(($consumedCount = count($consumedItems)) !== 3){
			throw new TransactionValidationException("Expected exactly 3 items to be consumed, got $consumedCount");
		}
		$this->validateConsumedItems($consumedItems);

		if(($createdCount = count($createdItems)) !== 1){
			throw new TransactionValidationException("Expected exactly 1 item to be created, got $createdCount");
		}
		$createdItem = $createdItems[0];
		if($createdItem->getCount() !== 1){
			throw new TransactionValidationException("Expected exactly 1 item to be created, got " . $createdItem->getCount());
		}

		$expectedResult = $this->recipe->getResultFor($this->template, $this->input, $this->addition);
		if($expectedResult === null || !$expectedResult->equalsExact($createdItem)){
			throw new TransactionValidationException("Invalid output item");
		}
	}
}
