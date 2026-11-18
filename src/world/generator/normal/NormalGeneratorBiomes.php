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

namespace pocketmine\world\generator\normal;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\data\bedrock\BiomeTypeIdMap;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\biome\Biome;
use pocketmine\world\generator\biome\GeneratorBiome;
use pocketmine\world\generator\object\OreType;
use pocketmine\world\generator\object\TreeType;
use pocketmine\world\generator\populator\Ore;
use pocketmine\world\generator\populator\Populator;
use pocketmine\world\generator\populator\TallGrass;
use pocketmine\world\generator\populator\Tree;

final class NormalGeneratorBiomes{

	/**
	 * @return GeneratorBiome[]
	 * @phpstan-return array<int, GeneratorBiome>
	 */
	public static function create() : array{
		$biomes = [
			new GeneratorBiome(self::biome(BiomeIds::OCEAN), 46, 58, self::repeat(VanillaBlocks::GRAVEL(), 5), [self::tallGrass(5)]),
			new GeneratorBiome(self::biome(BiomeIds::PLAINS), 63, 68, self::grassyCover(), [self::tallGrass(12)]),
			new GeneratorBiome(self::biome(BiomeIds::DESERT), 63, 74, self::sandyCover()),
			new GeneratorBiome(self::biome(BiomeIds::EXTREME_HILLS), 63, 127, self::grassyCover(), self::mountainPopulators()),
			new GeneratorBiome(self::biome(BiomeIds::FOREST), 63, 81, self::grassyCover(), self::forestPopulators(null)),
			new GeneratorBiome(self::biome(BiomeIds::TAIGA), 63, 81, self::snowyCover(), [self::trees(TreeType::SPRUCE, 10), self::tallGrass(1)]),
			new GeneratorBiome(self::biome(BiomeIds::SWAMPLAND), 62, 63, self::grassyCover()),
			new GeneratorBiome(self::biome(BiomeIds::RIVER), 58, 62, self::repeat(VanillaBlocks::DIRT(), 5), [self::tallGrass(5)]),
			new GeneratorBiome(self::biome(BiomeIds::ICE_PLAINS), 63, 74, self::snowyCover(), [self::tallGrass(5)]),
			new GeneratorBiome(self::biome(BiomeIds::EXTREME_HILLS_EDGE), 63, 97, self::grassyCover(), self::mountainPopulators()),
			new GeneratorBiome(self::biome(BiomeIds::BIRCH_FOREST), 63, 81, self::grassyCover(), self::forestPopulators(TreeType::BIRCH)),
		];

		$result = [];
		foreach($biomes as $generatorBiome){
			$result[$generatorBiome->getBiome()->getId()] = $generatorBiome;
		}
		return $result;
	}

	private static function biome(int $id) : Biome{
		return BiomeTypeIdMap::getInstance()->fromId($id) ?? throw new AssumptionFailedError("Biome ID $id is not registered");
	}

	/**
	 * @phpstan-return list<Block>
	 */
	private static function repeat(Block $block, int $count) : array{
		$result = [];
		for($i = 0; $i < $count; ++$i){
			$result[] = $block;
		}
		return $result;
	}

	/**
	 * @phpstan-return list<Block>
	 */
	private static function grassyCover() : array{
		return [VanillaBlocks::GRASS(), ...self::repeat(VanillaBlocks::DIRT(), 4)];
	}

	/**
	 * @phpstan-return list<Block>
	 */
	private static function sandyCover() : array{
		return [...self::repeat(VanillaBlocks::SAND(), 2), ...self::repeat(VanillaBlocks::SANDSTONE(), 3)];
	}

	/**
	 * @phpstan-return list<Block>
	 */
	private static function snowyCover() : array{
		return [VanillaBlocks::SNOW_LAYER(), VanillaBlocks::GRASS(), ...self::repeat(VanillaBlocks::DIRT(), 3)];
	}

	private static function tallGrass(int $baseAmount) : TallGrass{
		$tallGrass = new TallGrass();
		$tallGrass->setBaseAmount($baseAmount);
		return $tallGrass;
	}

	private static function trees(?TreeType $type, int $baseAmount) : Tree{
		$trees = new Tree($type);
		$trees->setBaseAmount($baseAmount);
		return $trees;
	}

	/**
	 * @phpstan-return list<Populator>
	 */
	private static function forestPopulators(?TreeType $type) : array{
		return [self::trees($type, 5), self::tallGrass(3)];
	}

	/**
	 * @phpstan-return list<Populator>
	 */
	private static function mountainPopulators() : array{
		$ores = new Ore();
		$ores->setOreTypes([
			new OreType(VanillaBlocks::EMERALD_ORE(), VanillaBlocks::STONE(), 11, 1, 0, 32)
		]);
		return [self::trees(null, 1), self::tallGrass(1), $ores];
	}
}
