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
 * Recipe usable in a smithing table. Consumes exactly one item from each of the template, input and addition slots
 * and produces exactly one output item.
 */
interface SmithingRecipe{

	public function getTemplate() : RecipeIngredient;

	public function getInput() : RecipeIngredient;

	public function getAddition() : RecipeIngredient;

	/**
	 * Returns the item produced for the given items, or null if they don't satisfy the recipe's ingredients.
	 * Counts of the given items are ignored.
	 */
	public function getResultFor(Item $template, Item $input, Item $addition) : ?Item;
}
