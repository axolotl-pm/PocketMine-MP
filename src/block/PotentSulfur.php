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
use pocketmine\block\utils\PotentSulfurState;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Living;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\sound\GeyserContinuousEruptionActiveSound;
use pocketmine\world\sound\GeyserContinuousEruptionStartSound;
use pocketmine\world\sound\GeyserEruptionActiveSound;
use pocketmine\world\sound\GeyserEruptionStartSound;
use function count;
use function max;
use function mt_rand;

final class PotentSulfur extends Opaque{

	private const MAX_WATER_DEPTH = 4;

	private const GAS_EFFECT_INTERVAL_TICKS = 10;
	private const GAS_EFFECT_DURATION_TICKS = 80;
	private const GAS_EFFECT_RANGE_BLOCKS = 3.0;

	private const PHASE_STEP_TICKS = 20;
	private const ERUPTION_SOUND_INTERVAL_TICKS = 40;

	private const DORMANT_MIN_STEPS = 15;
	private const DORMANT_MAX_STEPS = 30;
	private const DORMANT_STEPS_PER_EXTRA_WATER = 10;

	private const ERUPTING_MIN_STEPS = 1;
	private const ERUPTING_MAX_STEPS = 2;
	private const ERUPTING_STEPS_PER_EXTRA_WATER = 1;

	private const GEYSER_BASE_LAUNCH_SPEED = 0.3;
	private const GEYSER_LAUNCH_FORCE = 0.2;

	private const GEYSER_PLUME_BLOCKS_PER_WATER = 6;

	private PotentSulfurState $state = PotentSulfurState::DRY;

	/** Ticks until the next dose of noxious gas. */
	private int $gasEffectCooldown = 0;
	/** Ticks left in the current phase. Unused while continuous, which never ends. */
	private int $stateChangeCooldown = 0;
	/** Ticks until the looping eruption sound plays again. */
	private int $eruptionSoundCooldown = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->enum($this->state);
	}

	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();
		$tile = $this->getTile();
		if($tile !== null){
			$this->gasEffectCooldown = $tile->getGasEffectCooldown();
			$this->stateChangeCooldown = $tile->getStateChangeCooldown();
			$this->eruptionSoundCooldown = $tile->getEruptionSoundCooldown();
		}

		return $this;
	}

	public function writeStateToWorld() : void{
		parent::writeStateToWorld();
		$this->saveCooldowns();
	}

	private function saveCooldowns() : void{
		$tile = $this->getTile();
		if($tile !== null){
			$tile->setGasEffectCooldown($this->gasEffectCooldown);
			$tile->setStateChangeCooldown($this->stateChangeCooldown);
			$tile->setEruptionSoundCooldown($this->eruptionSoundCooldown);
		}
	}

	public function getPotentSulfurState() : PotentSulfurState{ return $this->state; }

	/** @return $this */
	public function setPotentSulfurState(PotentSulfurState $state) : self{
		$this->state = $state;
		return $this;
	}

	public function onPostPlace() : void{
		if($this->state->isErupting()){
			$this->startErupting();
		}

		$this->scheduleTickIfActive();
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->state = $this->computeState($blockReplace);

		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onNearbyBlockChange() : void{
		$state = $this->computeState($this);
		if($state !== $this->state){
			$this->applyState($state);
		}
	}

	private function getTile() : ?TilePotentSulfur{
		$tile = $this->position->getWorld()->getTile($this->position);

		return $tile instanceof TilePotentSulfur ? $tile : null;
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		if($this->state === PotentSulfurState::DRY){
			return;
		}

		$source = $this->findGasSource();
		if($source !== null){
			if(--$this->gasEffectCooldown <= 0){
				$this->gasEffectCooldown = self::GAS_EFFECT_INTERVAL_TICKS;
				$this->applyNoxiousGas($source);
			}

			if($this->state !== PotentSulfurState::CONTINUOUS){
				if($this->stateChangeCooldown <= 0){
					$this->stateChangeCooldown = $this->rollPhaseDuration($source);
				}
				if(--$this->stateChangeCooldown <= 0){
					$this->applyState($this->state === PotentSulfurState::DORMANT ? PotentSulfurState::ERUPTING : PotentSulfurState::DORMANT);
				}
			}

			if($this->state->isErupting()){
				$this->launchEntities($source);
				if(--$this->eruptionSoundCooldown <= 0){
					$this->eruptionSoundCooldown = self::ERUPTION_SOUND_INTERVAL_TICKS;
					$this->playEruptionSound($source);
				}
			}

			$this->saveCooldowns();
		}

		$world->scheduleDelayedBlockUpdate($this->position, 1);
	}

	private function applyState(PotentSulfurState $state) : void{
		$this->state = $state;
		$this->position->getWorld()->setBlock($this->position, $this);
		if($state->isErupting()){
			$this->startErupting();
		}

		$this->scheduleTickIfActive();
	}

	private function startErupting() : void{
		$this->eruptionSoundCooldown = 0;

		$this->position->getWorld()->addSound(
			$this->position->add(0.5, 0.5, 0.5),
			$this->state === PotentSulfurState::CONTINUOUS ? new GeyserContinuousEruptionStartSound() : new GeyserEruptionStartSound()
		);
	}

	private function scheduleTickIfActive() : void{
		if($this->state !== PotentSulfurState::DRY){
			$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 1);
		}
	}

	private function computeState(Block $block) : PotentSulfurState{
		$above = $block->getSide(Facing::UP);
		if(!($above instanceof Water) || !$above->isSource()){
			return PotentSulfurState::DRY;
		}

		$below = $block->getSide(Facing::DOWN);
		if($below instanceof Lava && $below->isSource()){
			return PotentSulfurState::CONTINUOUS;
		}

		if($below->getTypeId() !== BlockTypeIds::MAGMA){
			return PotentSulfurState::WET;
		}

		if($this->state === PotentSulfurState::ERUPTING){
			return PotentSulfurState::ERUPTING;
		}
		if($this->state !== PotentSulfurState::DORMANT){
			//not a geyser until now, so don't inherit a countdown from a previous life
			$this->stateChangeCooldown = 0;
		}

		return PotentSulfurState::DORMANT;
	}

	private function rollPhaseDuration(Block $source) : int{
		$extraWater = $this->getWaterDepth($source) - 1;
		$steps = $this->state === PotentSulfurState::DORMANT ?
			mt_rand(self::DORMANT_MIN_STEPS, self::DORMANT_MAX_STEPS) + self::DORMANT_STEPS_PER_EXTRA_WATER * $extraWater :
			mt_rand(self::ERUPTING_MIN_STEPS, self::ERUPTING_MAX_STEPS) + self::ERUPTING_STEPS_PER_EXTRA_WATER * $extraWater;

		return max(1, $steps) * self::PHASE_STEP_TICKS;
	}

	/**
	 * Plays the sound where the plume breaks the surface, unlike the sound that announces the eruption.
	 */
	private function playEruptionSound(Block $source) : void{
		$this->position->getWorld()->addSound(
			$source->position->add(0.5, 0.5, 0.5),
			$this->state === PotentSulfurState::CONTINUOUS ? new GeyserContinuousEruptionActiveSound() : new GeyserEruptionActiveSound()
		);
	}

	private function findGasSource() : ?Block{
		$maxY = $this->position->getFloorY() + self::MAX_WATER_DEPTH + 1;

		$block = $this->getSide(Facing::UP);
		while($block->position->getFloorY() <= $maxY){
			if($block instanceof Water && $block->isSource()){
				$block = $block->getSide(Facing::UP);
				continue;
			}

			return self::isGeyserPassable($block) ? $block : null;
		}

		return null;
	}

	private static function isGeyserPassable(Block $block) : bool{
		return $block->getTypeId() === BlockTypeIds::AIR || $block instanceof Water || count($block->getCollisionBoxes()) === 0;
	}

	private function getWaterDepth(Block $source) : int{
		return $source->position->getFloorY() - $this->position->getFloorY() - 1;
	}

	private function applyNoxiousGas(Block $source) : void{
		$world = $this->position->getWorld();
		$bb = AxisAlignedBB::one()
			->offset($source->position->x, $source->position->y, $source->position->z)
			->expand(2.5, 0.0, 2.5);

		foreach($world->getNearbyEntities($bb) as $entity){
			if(!($entity instanceof Living)){
				continue;
			}

			$eyePos = $entity->getEyePos();
			if($this->gasCanReach($source, $eyePos)){
				$entity->getEffects()->add(new EffectInstance(VanillaEffects::NAUSEA(), self::GAS_EFFECT_DURATION_TICKS, 0, true, true));
			}
		}
	}

	/**
	 * Returns whether gas can reach to the target pos.
	 */
	private function gasCanReach(Block $source, Vector3 $eyePos) : bool{
		$world = $this->position->getWorld();
		if(!self::isGeyserPassable($world->getBlock($eyePos))){
			return false;
		}
		if($eyePos->distanceSquared($source->position->add(0.5, 0.5, 0.5)) > self::GAS_EFFECT_RANGE_BLOCKS ** 2){
			return false;
		}

		$feetPos = new Vector3($eyePos->x, $eyePos->y - 1, $eyePos->z);
		$feetBlock = $world->getBlock($feetPos);
		if(!($feetBlock instanceof Water) || !$feetBlock->isSource()){
			return false;
		}

		return $world->hasLineOfSight($source->position->add(0.5, -0.5, 0.5), $feetPos);
	}

	/**
	 * Returns how far up the plume gets before something blocks it.
	 */
	private function getPlumeHeight(int $waterDepth) : int{
		$maxHeight = self::GEYSER_PLUME_BLOCKS_PER_WATER * $waterDepth;

		for($i = 0; $i < $maxHeight; $i++){
			$block = $this->getSide(Facing::UP, $i + 1);
			if(!self::isGeyserPassable($block)){
				return $i;
			}
		}

		return $maxHeight;
	}

	private function launchEntities(Block $source) : void{
		$waterDepth = $this->getWaterDepth($source);
		$plumeHeight = $this->getPlumeHeight($waterDepth);
		if($plumeHeight <= 0){
			return;
		}

		$bb = AxisAlignedBB::one()
			->offset($this->position->x, $this->position->y + 1, $this->position->z)
			->extend(Facing::UP, $plumeHeight - 1);
		$maxSpeed = self::GEYSER_BASE_LAUNCH_SPEED + $waterDepth * 0.1;

		foreach($this->position->getWorld()->getNearbyEntities($bb) as $entity){
			if($entity instanceof Player && $entity->isFlying()){
				continue;
			}

			$entity->resetFallDistance();
			if($entity->getMotion()->y < $maxSpeed){
				$entity->addMotion(0, self::GEYSER_LAUNCH_FORCE, 0);
			}
		}
	}
}
