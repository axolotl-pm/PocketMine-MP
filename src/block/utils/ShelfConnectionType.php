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

namespace pocketmine\block\utils;

enum ShelfConnectionType : int{
	case UNCONNECTED = 0;
	case RIGHT = 1;
	case CENTER = 2;
	case LEFT = 3;

	public function isConnected() : bool{
		return $this !== self::UNCONNECTED;
	}

	public function canBeNeighbor(bool $onLeft) : bool{
		return match($onLeft){
			true => $this === self::LEFT || $this === self::CENTER,
			false => $this === self::CENTER || $this === self::RIGHT
		};
	}

	public function getMaximumNeighborCount(bool $onLeft, ?self $nearestNeighbor) : int{
		return match($this){
			self::UNCONNECTED => 0,
			self::LEFT => $onLeft ? 0 : ($nearestNeighbor === self::RIGHT ? 1 : 2),
			self::CENTER => 1,
			self::RIGHT => $onLeft ? ($nearestNeighbor === self::LEFT ? 1 : 2) : 0
		};
	}

	public static function fromGroupPosition(int $position, int $groupSize) : self{
		if($groupSize < 1 || $groupSize > 3){
			throw new \InvalidArgumentException("Group size must be between 1 and 3");
		}
		if($position < 0 || $position >= $groupSize){
			throw new \InvalidArgumentException("Group position must be within group size");
		}

		return match($groupSize){
			1 => self::UNCONNECTED,
			2 => $position === 0 ? self::LEFT : self::RIGHT,
			3 => match($position){
				0 => self::LEFT,
				1 => self::CENTER,
				2 => self::RIGHT
			}
		};
	}
}
