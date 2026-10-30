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

namespace pocketmine\player;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\promise\Promise;

/**
 * Handles storage of player data. Implementations must treat player names in a case-insensitive manner.
 */
interface PlayerDataProvider{

	/**
	 * Returns whether there are any data associated with the given player name.
	 */
	public function hasData(string $name) : bool;

	/**
	 * Returns a Promise that resolves to the data for the given player name.
	 *
	 * @phpstan-return Promise<CompoundTag>
	 * @throws PlayerDataLoadException
	 */
	public function loadData(string $name) : Promise;

	/**
	 * Saves data for the give player name.
	 *
	 * @throws PlayerDataSaveException
	 */
	public function saveData(string $name, CompoundTag $data) : void;
}
