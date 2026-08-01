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

namespace pocketmine\block\tile;

use pocketmine\nbt\tag\CompoundTag;

/**
 * This tile serves no purpose on bedrock (stuff are done in client-side), while Java saves the countdown for the block
 * to erupt in the tile entity.
 */
final class PotentSulfur extends Tile{

	public const NO_COUNTDOWN = -1;

	private int $waitingCountdownTicks = self::NO_COUNTDOWN;

	public function readSaveData(CompoundTag $nbt) : void{
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
	}

	public function getWaitingCountdownTicks() : int{ return $this->waitingCountdownTicks; }

	public function setWaitingCountdownTicks(int $waitingCountdownTicks) : void{
		$this->waitingCountdownTicks = $waitingCountdownTicks;
	}
}
