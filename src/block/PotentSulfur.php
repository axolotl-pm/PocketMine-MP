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

use pocketmine\math\Facing;
use pocketmine\world\sound\Sound;

class PotentSulfur extends Opaque{

	public function readStateFromWorld() : Block{
		$result = parent::readStateFromWorld();
		if($result->getTypeId() !== $this->getTypeId()){
			return $result;
		}

		return $this->computeVariant();
	}

	public function onNearbyBlockChange() : void{
		$result = $this->computeVariant();
		if($result->getTypeId() !== $this->getTypeId()){
			$this->position->getWorld()->setBlock($this->position, $result);

			if(($sound = $this->getFormationSound()) !== null){
				$this->position->getWorld()->addSound($this->position->add(0.5, 0.5, 0.5), $sound);
			}
		}
	}

	/**
	 * Returns the sound played once when this block is the result of a
	 * variant transition, or null if it has no characteristic sound.
	 */
	public function getFormationSound() : ?Sound{
		return null;
	}

	private function computeVariant() : PotentSulfur{
		$above = $this->getSide(Facing::UP);
		if(!($above instanceof Water) || !$above->isSource()){
			return VanillaBlocks::POTENT_SULFUR();
		}

		$below = $this->getSide(Facing::DOWN);
		if($below instanceof Lava && $below->isSource()){
			return VanillaBlocks::CONTINUOUS_POTENT_SULFUR();
		}

		if($below->getTypeId() === BlockTypeIds::MAGMA){
			return VanillaBlocks::CYCLING_POTENT_SULFUR();
		}

		return VanillaBlocks::WET_POTENT_SULFUR();
	}
}
