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

	private int $gasEffectCooldown = 0;
	private int $stateChangeCooldown = 0;
	private int $eruptionSoundCooldown = 0;

	public function readSaveData(CompoundTag $nbt) : void{
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
	}

	public function getGasEffectCooldown() : int{ return $this->gasEffectCooldown; }

	public function setGasEffectCooldown(int $gasEffectCooldown) : void{
		$this->gasEffectCooldown = $gasEffectCooldown;
	}

	public function getStateChangeCooldown() : int{ return $this->stateChangeCooldown; }

	public function setStateChangeCooldown(int $stateChangeCooldown) : void{
		$this->stateChangeCooldown = $stateChangeCooldown;
	}

	public function getEruptionSoundCooldown() : int{ return $this->eruptionSoundCooldown; }

	public function setEruptionSoundCooldown(int $eruptionSoundCooldown) : void{
		$this->eruptionSoundCooldown = $eruptionSoundCooldown;
	}
}
