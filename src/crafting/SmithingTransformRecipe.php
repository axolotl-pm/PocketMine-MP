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

namespace pocketmine\crafting;

use pocketmine\item\Item;

/**
 * Smithing recipe which turns the input item into a different item, preserving its NBT (damage, enchantments,
 * custom name, trim, etc.). Used for netherite upgrades.
 */
class SmithingTransformRecipe implements SmithingRecipe{

	private readonly Item $result;

	public function __construct(
		private readonly RecipeIngredient $template,
		private readonly RecipeIngredient $input,
		private readonly RecipeIngredient $addition,
		Item $result
	){
		$this->result = clone $result;
	}

	public function getTemplate() : RecipeIngredient{ return $this->template; }

	public function getInput() : RecipeIngredient{ return $this->input; }

	public function getAddition() : RecipeIngredient{ return $this->addition; }

	/**
	 * Returns the base result item of this recipe, without any properties copied from the input item.
	 */
	public function getResult() : Item{
		return clone $this->result;
	}

	public function getResultFor(Item $template, Item $input, Item $addition) : ?Item{
		if(!$this->template->accepts($template) || !$this->input->accepts($input) || !$this->addition->accepts($addition)){
			return null;
		}

		//vanilla carries everything (enchantments, damage, name, trim) over to the upgraded item
		return $this->getResult()->setNamedTag($input->getNamedTag());
	}
}
