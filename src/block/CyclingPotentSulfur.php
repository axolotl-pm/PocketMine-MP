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

use pocketmine\block\tile\PotentSulfur as TilePotentSulfur;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\world\sound\GeyserEruptionActiveSound;
use pocketmine\world\sound\GeyserEruptionStartSound;
use pocketmine\world\sound\Sound;
use function max;
use function mt_rand;

final class CyclingPotentSulfur extends EruptivePotentSulfur{

	private const DORMANT_MIN_HEARTBEATS = 30; //15 seconds
	private const DORMANT_MAX_HEARTBEATS = 60; //30 seconds
	private const DORMANT_HEARTBEATS_PER_EXTRA_DEPTH = 20; //10 seconds

	private const ERUPTING_MIN_HEARTBEATS = 2; //1 second
	private const ERUPTING_MAX_HEARTBEATS = 4; //2 seconds
	private const ERUPTING_HEARTBEATS_PER_EXTRA_DEPTH = 2; //1 second

	private bool $erupting = false;

	/** Heartbeats left before this phase flips to the other one. */
	private int $heartbeatsUntilPhaseShift = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->bool($this->erupting);
	}

	public function readStateFromWorld() : Block{
		$result = parent::readStateFromWorld();
		if($result->getTypeId() !== $this->getTypeId()){
			return $result;
		}

		$tile = $this->position->getWorld()->getTile($this->position);
		if($tile instanceof TilePotentSulfur){
			$this->heartbeatsUntilPhaseShift = $tile->getHeartbeatsUntilPhaseShift();
		}

		return $this;
	}

	public function writeStateToWorld() : void{
		parent::writeStateToWorld();

		$tile = $this->position->getWorld()->getTile($this->position);
		if($tile instanceof TilePotentSulfur){
			$tile->setHeartbeatsUntilPhaseShift($this->heartbeatsUntilPhaseShift);
		}
	}

	public function isErupting() : bool{ return $this->erupting; }

	/** @return $this */
	public function setErupting(bool $erupting) : self{
		$this->erupting = $erupting;
		return $this;
	}

	public function getEruptionStartSound() : Sound{
		return new GeyserEruptionStartSound();
	}

	public function getEruptionPulseSound() : Sound{
		return new GeyserEruptionActiveSound();
	}

	protected function onGeyserHeartbeat(?Block $outlet) : void{
		parent::onGeyserHeartbeat($outlet);

		if(--$this->heartbeatsUntilPhaseShift <= 0){
			if($outlet !== null){
				$this->shiftPhase($outlet);
			}

			//Even when outlet is blocks keep rerolling a fresh
			//budget so it doesn't erupt the instant it reopens.
			$this->heartbeatsUntilPhaseShift = $this->rollPhaseShiftHeartbeats($outlet);
			$this->position->getWorld()->setBlock($this->position, $this);
		}
	}

	private function shiftPhase(Block $outlet) : void{
		$this->erupting = !$this->erupting;

		if($this->erupting){
			$soundPosition = $outlet->position->add(0.5, 0.5, 0.5);
			$this->position->getWorld()->addSound($soundPosition, $this->getEruptionStartSound());
			$this->position->getWorld()->addSound($soundPosition, $this->getEruptionPulseSound());
		}
	}

	private function rollPhaseShiftHeartbeats(?Block $outlet) : int{
		$extraDepth = $outlet !== null ? max(0, $this->getWaterColumnDepth($outlet) - 1) : 0;

		return $this->erupting
			? mt_rand(self::ERUPTING_MIN_HEARTBEATS, self::ERUPTING_MAX_HEARTBEATS) + self::ERUPTING_HEARTBEATS_PER_EXTRA_DEPTH * $extraDepth
			: mt_rand(self::DORMANT_MIN_HEARTBEATS, self::DORMANT_MAX_HEARTBEATS) + self::DORMANT_HEARTBEATS_PER_EXTRA_DEPTH * $extraDepth;
	}
}
