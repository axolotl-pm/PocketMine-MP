<?php

declare(strict_types=1);

namespace pocketmine\block\utils;

enum ShelfSlot : int{
	case LEFT = 0;
	case MIDDLE = 1;
	case RIGHT = 2;

	public static function fromBlockFaceCoordinate(float $x) : self{
		if($x < 0 || $x > 1){
			throw new \InvalidArgumentException("X must be between 0 and 1, got $x");
		}

		return self::from(match(true){
			$x < 1 / 3 => 0,
			$x < 2 / 3 => 1,
			default => 2
		});
	}
}
