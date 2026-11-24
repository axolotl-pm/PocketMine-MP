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

namespace pocketmine\item;

use pocketmine\entity\Location;
use pocketmine\entity\projectile\Egg as EggEntity;
use pocketmine\entity\projectile\Throwable;
use pocketmine\player\Player;

class Egg extends ProjectileItem{
	private EggType $eggType;

	public function __construct(ItemIdentifier $identifier, string $name, EggType $boatType){
		parent::__construct($identifier, $name);
		$this->eggType = $boatType;
	}

	public function getType() : EggType{
		return $this->eggType;
	}

	public function getMaxStackSize() : int{
		return 16;
	}

	protected function createEntity(Location $location, Player $thrower) : Throwable{
		return new EggEntity($location, $thrower, $this->eggType);
	}

	public function getThrowForce() : float{
		return 1.5;
	}
}
