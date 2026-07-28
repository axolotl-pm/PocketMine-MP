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

class PotentSulfur extends Tile{
	private const TAG_COUNTDOWN = "countdown";

	public const NO_COUNTDOWN = -1;

	private int $waitingCountdown = self::NO_COUNTDOWN;

	public function readSaveData(CompoundTag $nbt) : void{
		$this->waitingCountdown = $nbt->getInt(self::TAG_COUNTDOWN, self::NO_COUNTDOWN);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setInt(self::TAG_COUNTDOWN, $this->waitingCountdown);
	}

	public function getWaitingCountdown() : int{ return $this->waitingCountdown; }

	public function setWaitingCountdown(int $waitingCountdown) : void{
		$this->waitingCountdown = $waitingCountdown;
	}

	public function resetCountdown() : void{
		$this->waitingCountdown = self::NO_COUNTDOWN;
	}
}
