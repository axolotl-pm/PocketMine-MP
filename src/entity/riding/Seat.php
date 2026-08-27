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

namespace pocketmine\entity\riding;

use pocketmine\math\Vector3;

/**
 * Represents a seat configuration on a {@link Mountable} entity.
 */
final class Seat{

	/**
	 * @param Vector3    $position          Offset relative to vehicle origin (unscaled)
	 * @param int        $minRiderCount     Minimum rider count required for this seat to be eligible
	 * @param int        $maxRiderCount     Maximum rider count for this seat (0 to inherit vehicle seat count)
	 * @param float|null $lockRiderRotation Rotation clamp angle in degrees, or null for no clamp
	 * @param float      $rotateRiderBy     Yaw rotation offset relative to vehicle
	 */
	public function __construct(
		public readonly Vector3 $position,
		public readonly int $minRiderCount = 0,
		public readonly int $maxRiderCount = 0,
		public readonly ?float $lockRiderRotation = null,
		public readonly float $rotateRiderBy = 0.0
	){
	}

	/**
	 * Returns whether this seat can be used with the given number of riders.
	 */
	public function isEligibleFor(int $riderCount, int $seatCount) : bool{
		if($this->minRiderCount === 0 && $this->maxRiderCount === 0){
			return true;
		}
		$max = $this->maxRiderCount !== 0 ? $this->maxRiderCount : $seatCount;
		return $riderCount >= $this->minRiderCount && $riderCount <= $max;
	}
}
