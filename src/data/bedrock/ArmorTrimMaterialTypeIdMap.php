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

namespace pocketmine\data\bedrock;

use pocketmine\data\bedrock\ArmorTrimMaterialTypeIds as Ids;
use pocketmine\item\ArmorTrimMaterial;
use pocketmine\utils\SingletonTrait;
use function array_key_exists;
use function spl_object_id;

final class ArmorTrimMaterialTypeIdMap{
	use SingletonTrait;

	/**
	 * @var ArmorTrimMaterial[]
	 * @phpstan-var array<string, ArmorTrimMaterial>
	 */
	private array $idToEnum = [];
	/**
	 * @var string[]
	 * @phpstan-var array<int, string>
	 */
	private array $enumToId = [];

	public function __construct(){
		foreach(ArmorTrimMaterial::cases() as $case){
			$this->register(match($case){
				ArmorTrimMaterial::AMETHYST => Ids::AMETHYST,
				ArmorTrimMaterial::COPPER => Ids::COPPER,
				ArmorTrimMaterial::DIAMOND => Ids::DIAMOND,
				ArmorTrimMaterial::EMERALD => Ids::EMERALD,
				ArmorTrimMaterial::GOLD => Ids::GOLD,
				ArmorTrimMaterial::IRON => Ids::IRON,
				ArmorTrimMaterial::LAPIS => Ids::LAPIS,
				ArmorTrimMaterial::NETHERITE => Ids::NETHERITE,
				ArmorTrimMaterial::QUARTZ => Ids::QUARTZ,
				ArmorTrimMaterial::REDSTONE => Ids::REDSTONE,
				ArmorTrimMaterial::RESIN => Ids::RESIN
			}, $case);
		}
	}

	public function register(string $stringId, ArmorTrimMaterial $material) : void{
		$this->idToEnum[$stringId] = $material;
		$this->enumToId[spl_object_id($material)] = $stringId;
	}

	public function fromId(string $id) : ?ArmorTrimMaterial{
		return $this->idToEnum[$id] ?? null;
	}

	public function toId(ArmorTrimMaterial $material) : string{
		$k = spl_object_id($material);
		if(!array_key_exists($k, $this->enumToId)){
			throw new \InvalidArgumentException("Missing mapping for armor trim material " . $material->name);
		}
		return $this->enumToId[$k];
	}
}
