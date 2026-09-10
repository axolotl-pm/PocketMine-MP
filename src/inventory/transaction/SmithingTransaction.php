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
		private readonly SmithingRecipe $recipe
	){
		parent::__construct($source);
	}

	public function getRecipe() : SmithingRecipe{ return $this->recipe; }

	/**
	 * Tries to assign the given items to the recipe's template, input and addition slots, returning the recipe's
	 * result for that assignment. The items received from the client are unordered, so every permutation is tried.
	 *
	 * @param Item[] $items
	 * @phpstan-param list<Item> $items
	 */
	private function matchInputs(array $items) : ?Item{
		foreach($items as $i => $template){
			foreach($items as $j => $input){
				if($j === $i){
					continue;
				}
				foreach($items as $k => $addition){
					if($k === $i || $k === $j){
						continue;
					}
					$result = $this->recipe->getResultFor($template, $input, $addition);
					if($result !== null){
						return $result;
					}
				}
			}
		}

		return null;
	}

	public function validate() : void{
		if(count($this->actions) < 1){
			throw new TransactionValidationException("Transaction must have at least one action to be executable");
		}

		$inputs = [];
		$outputs = [];
		//matchItems() can't be used here - the result may stack with one of the inputs, e.g. re-applying the trim an
		//armour piece already has
		$this->separateCreatedAndConsumedItems($outputs, $inputs);

		if(($inputCount = count($inputs)) !== 3){
			throw new TransactionValidationException("Expected exactly 3 input items, got $inputCount");
		}
		if(($outputCount = count($outputs)) !== 1){
			throw new TransactionValidationException("Expected exactly 1 output item, got $outputCount");
		}
		foreach($inputs as $input){
			if($input->getCount() !== 1){
				throw new TransactionValidationException("Expected exactly 1 of each input item to be consumed, got " . $input->getCount() . " of " . $input->getName());
			}
		}

		$expectedOutput = $this->matchInputs($inputs);
		if($expectedOutput === null){
			throw new TransactionValidationException("The given input items don't match the recipe's ingredients");
		}

		$output = $outputs[0];
		if($output->getCount() !== 1){
			throw new TransactionValidationException("Expected exactly 1 output item, got " . $output->getCount());
		}
		if(!$expectedOutput->equalsExact($output)){
			throw new TransactionValidationException("Invalid output item");
		}
	}
}
