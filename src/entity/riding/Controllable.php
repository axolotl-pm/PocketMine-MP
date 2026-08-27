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

use pocketmine\entity\Entity;

/**
 * Implemented by mountable entities that can be steered by a rider.
 */
interface Controllable extends Mountable{

	/**
	 * Returns the seat index that controls the vehicle.
	 */
	public function getControllingSeatIndex() : int;

	/**
	 * Called once per tick with the controlling rider's most recent movement input, zero included.
	 *
	 * @param float $strafe  Sideways input (-1.0 to 1.0, positive is left)
	 * @param float $forward Forward input (-1.0 to 1.0, positive is forward)
	 */
	public function onRiderInput(Entity $rider, float $strafe, float $forward) : void;

	/**
	 * Called when the controlling rider charges or releases a jump.
	 *
	 * @param float $charge Jump charge progress (0.4 to 1.0)
	 */
	public function onRiderJump(Entity $rider, float $charge, ChargingState $state) : void;

	/**
	 * Returns the jump power of this entity when fully charged, or 0.0 if the entity cannot jump.
	 */
	public function getJumpStrength() : float;
}
