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

namespace pocketmine\entity\projectile;

use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\item\EggType;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\particle\ItemBreakParticle;

class Egg extends Throwable{
	public static function getNetworkTypeId() : string{ return EntityIds::EGG; }

	public const TAG_TYPE = "type"; // TAG_String

	private EggType $eggType = EggType::NORMAL;

	public function __construct(Location $location, ?Entity $shootingEntity, ?EggType $eggType = null, ?CompoundTag $nbt = null){
		parent::__construct($location, $shootingEntity, $nbt);
		if($eggType !== null){
			$this->setEggType($eggType);
		}
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$typeName = $nbt->getString(self::TAG_TYPE, "temperate");
		$this->eggType = match($typeName){
			"warm" => EggType::BROWN,
			"cold" => EggType::BLUE,
			default => EggType::NORMAL,
		};
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setString(self::TAG_TYPE, match($this->eggType) {
			EggType::BROWN => "warm",
			EggType::BLUE => "cold",
			default => "temperate"
		});
		return $nbt;
	}

	/**
	 * @return EggType
	 */
	public function getEggType() : EggType{
		return $this->eggType;
	}

	/**
	 * @param EggType $eggType
	 */
	public function setEggType(EggType $eggType) : void{
		$this->eggType = $eggType;
	}

	//TODO: spawn chickens on collision

	protected function onHit(ProjectileHitEvent $event) : void{
		for($i = 0; $i < 6; ++$i){
			$this->getWorld()->addParticle($this->location, new ItemBreakParticle(match ($this->eggType) {
				EggType::BLUE => VanillaItems::BLUE_EGG(),
				EggType::BROWN => VanillaItems::BROWN_EGG(),
				EggType::NORMAL => VanillaItems::EGG(),
			}));
		}
	}
}
