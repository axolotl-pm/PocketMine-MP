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

namespace pocketmine\event\player;

use pocketmine\crafting\SmithingRecipe;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\inventory\transaction\SmithingTransaction;
use pocketmine\item\Item;
use pocketmine\player\Player;

/**
 * Called when a player takes the result of a smithing table recipe, e.g. a netherite upgrade or an armor trim.
 */
class PlayerItemSmithEvent extends PlayerEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		Player $player,
		private readonly SmithingTransaction $transaction,
		private readonly SmithingRecipe $recipe,
		private readonly Item $outputItem
	){
		$this->player = $player;
	}

	/**
	 * Returns the inventory transaction involved in this smith event.
	 */
	public function getTransaction() : SmithingTransaction{
		return $this->transaction;
	}

	/**
	 * Returns the smithing recipe used.
	 */
	public function getRecipe() : SmithingRecipe{
		return $this->recipe;
	}

	/**
	 * Returns the smithing template consumed, e.g. a netherite upgrade or an armor trim template.
	 */
	public function getTemplateItem() : Item{
		return $this->transaction->getTemplate();
	}

	/**
	 * Returns the item which was upgraded or decorated.
	 */
	public function getInputItem() : Item{
		return $this->transaction->getInput();
	}

	/**
	 * Returns the material consumed, e.g. a netherite ingot or an armor trim material.
	 */
	public function getAdditionItem() : Item{
		return $this->transaction->getAddition();
	}

	/**
	 * Returns the item produced by the recipe.
	 */
	public function getOutputItem() : Item{
		return clone $this->outputItem;
	}
}
