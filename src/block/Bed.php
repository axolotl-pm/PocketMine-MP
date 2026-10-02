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

namespace pocketmine\block;

use pocketmine\block\tile\Bed as TileBed;
use pocketmine\block\utils\Colored;
use pocketmine\block\utils\ColoredTrait;
use pocketmine\block\utils\DyeColor;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;

class Bed extends BaseBed implements Colored{
	use ColoredTrait;

	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();
		//read extra state information from the tile - this is an ugly hack
		$tile = $this->position->getWorld()->getTile($this->position);
		if($tile instanceof TileBed){
			$this->color = $tile->getColor();
		}else{
			$this->color = DyeColor::RED; //legacy pre-1.1 beds don't have tiles
		}

		return $this;
	}

	public function writeStateToWorld() : void{
		parent::writeStateToWorld();
		//extra block properties storage hack
		$tile = $this->position->getWorld()->getTile($this->position);
		if($tile instanceof TileBed){
			$tile->setColor($this->color);
		}
	}

	public function onEntityLand(Entity $entity) : ?float{
		if($entity instanceof Living && $entity->isSneaking()){
			return null;
		}
		$entity->fallDistance *= 0.5;
		return $entity->getMotion()->y * -3 / 4; // 2/3 in Java, according to the wiki
	}

	public function onPlayerStopSleep() : void{
		$this->setOccupied(false);
		$this->getPosition()->getWorld()->setBlock($this->getPosition()->asVector3(), $this);
	}

	public function getMaxStackSize() : int{ return 1; }
}
