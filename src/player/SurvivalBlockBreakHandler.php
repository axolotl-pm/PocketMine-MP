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

namespace pocketmine\player;

use pocketmine\block\Block;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\world\particle\BlockPunchParticle;
use pocketmine\world\sound\BlockPunchSound;
use function abs;

final class SurvivalBlockBreakHandler{

	public const DEFAULT_FX_INTERVAL_TICKS = 5;

	private int $fxTicker = 0;
	private float $breakSpeed;
	private float $breakProgress = 0;

	public function __construct(
		private Player $player,
		private Vector3 $blockPos,
		private Block $block,
		private int $targetedFace,
		private int $maxPlayerDistance,
		private int $fxTickInterval = self::DEFAULT_FX_INTERVAL_TICKS
	){
		$this->breakSpeed = $this->calculateBreakProgressPerTick();
		if($this->breakSpeed > 0){
			$this->player->getWorld()->broadcastPacketToViewers(
				$this->blockPos,
				LevelEventPacket::create(LevelEvent::BLOCK_START_BREAK, (int) (65535 * $this->breakSpeed), $this->blockPos)
			);
		}
	}

	/**
	 * Returns the calculated break speed as percentage progress per game tick.
	 */
	private function calculateBreakProgressPerTick() : float{
		if(!$this->block->getBreakInfo()->isBreakable()){
			return 0.0;
		}

		$hardness = $this->block->getBreakInfo()->getHardness();
		if($hardness <= 0.0){
			return 1.0;
		}

		$item = $this->player->getInventory()->getItemInHand();

		$speed = $item->getMiningEfficiency(($this->block->getBreakInfo()->getToolType() & $item->getBlockToolType()) !== 0);

		$effectManager = $this->player->getEffects();
		$hasteLevel = 0;

		if(($haste = $effectManager->get(VanillaEffects::HASTE())) !== null){
			$hasteLevel = $haste->getEffectLevel();
		}
		if(($conduit = $effectManager->get(VanillaEffects::CONDUIT_POWER())) !== null){
			$hasteLevel = max($hasteLevel, $conduit->getEffectLevel());
		}

		if($hasteLevel > 0){
			$speed *= 1.0 + (0.2 * $hasteLevel);
		}

		if(($fatigue = $effectManager->get(VanillaEffects::MINING_FATIGUE())) !== null){
			$speed *= pow(0.300000011920929, $fatigue->getEffectLevel());
		}

		if(!$this->player->isOnGround() && !$this->player->getAllowFlight()){
			$speed *= 0.2;
		}

		if($this->player->isUnderwater()){
			$helmet = $this->player->getArmorInventory()->getHelmet();
			if(!$helmet->hasEnchantment(VanillaEnchantments::AQUA_AFFINITY())){
				$speed *= 0.2;
			}
		}

		$damage = $speed / $hardness;
		$isCompatible = $this->block->getBreakInfo()->isToolCompatible(VanillaItems::AIR()) || $this->block->getBreakInfo()->isToolCompatible($item);

		return $damage * ($isCompatible ? 0.033333335 : 0.0099999998);
	}

	public function update() : bool{
		if($this->player->getPosition()->distanceSquared($this->blockPos->add(0.5, 0.5, 0.5)) > $this->maxPlayerDistance ** 2){
			return false;
		}

		$newBreakSpeed = $this->calculateBreakProgressPerTick();
		if(abs($newBreakSpeed - $this->breakSpeed) > 0.0001){
			$this->breakSpeed = $newBreakSpeed;
			$this->player->getWorld()->broadcastPacketToViewers(
				$this->blockPos,
				LevelEventPacket::create(LevelEvent::BLOCK_BREAK_SPEED, (int) (65535 * $this->breakSpeed), $this->blockPos)
			);
		}

		$this->breakProgress += $this->breakSpeed;

		if(($this->fxTicker++ % $this->fxTickInterval) === 0 && $this->breakProgress < 1){
			$this->player->getWorld()->addParticle($this->blockPos, new BlockPunchParticle($this->block, $this->targetedFace));
			$this->player->getWorld()->addSound($this->blockPos, new BlockPunchSound($this->block));
			$this->player->broadcastAnimation(new ArmSwingAnimation($this->player), $this->player->getViewers());
		}

		return $this->breakProgress < 1;
	}

	public function getBlockPos() : Vector3{
		return $this->blockPos;
	}

	public function getTargetedFace() : int{
		return $this->targetedFace;
	}

	public function setTargetedFace(int $face) : void{
		Facing::validate($face);
		$this->targetedFace = $face;
	}

	public function getBreakSpeed() : float{
		return $this->breakSpeed;
	}

	public function getBreakProgress() : float{
		return $this->breakProgress;
	}

	public function __destruct(){
		if($this->player->getWorld()->isInLoadedTerrain($this->blockPos)){
			$this->player->getWorld()->broadcastPacketToViewers(
				$this->blockPos,
				LevelEventPacket::create(LevelEvent::BLOCK_STOP_BREAK, 0, $this->blockPos)
			);
		}
	}
}