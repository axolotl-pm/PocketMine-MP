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

use pocketmine\item\Armor;
use pocketmine\item\ArmorTrim;
use pocketmine\item\ArmorTrimMaterial;
use pocketmine\item\ArmorTrimPattern;
use pocketmine\item\Item;

/**
 * Smithing recipe which applies a decorative trim to a piece of armour. The pattern is determined by the template
 * item and the material by the addition item. Any trim already present on the armour is replaced.
 */
class SmithingTrimRecipe implements SmithingRecipe{

	public function __construct(
		private readonly RecipeIngredient $template,
		private readonly RecipeIngredient $input,
		private readonly RecipeIngredient $addition
	){}

	public function getTemplate() : RecipeIngredient{ return $this->template; }

	public function getInput() : RecipeIngredient{ return $this->input; }

	public function getAddition() : RecipeIngredient{ return $this->addition; }

	public function getResultFor(Item $template, Item $input, Item $addition) : ?Item{
		if(!$this->template->accepts($template) || !$this->input->accepts($input) || !$this->addition->accepts($addition)){
			return null;
		}
		if(!$input instanceof Armor){
			return null;
		}

		$pattern = ArmorTrimPattern::fromItem($template);
		$material = ArmorTrimMaterial::fromItem($addition);
		if($pattern === null || $material === null){
			return null;
		}

		return (clone $input)->setTrim(new ArmorTrim($material, $pattern));
	}
}
