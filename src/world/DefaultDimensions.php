<?php

declare(strict_types=1);

namespace pocketmine\world;

use pocketmine\data\bedrock\BiomeTypeIdMap;
use pocketmine\network\mcpe\protocol\types\DimensionNameIds;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\biome\Biome;

/**
 * Default dimensions that are reserved by the client.
 * These are always registered and cannot be unregistered or replaced.
 */
final class DefaultDimensions{

	private static Dimension $overworld;
	private static Dimension $nether;
	private static Dimension $theEnd;

	private static function init() : void{
		self::$overworld = Dimension::createVanilla(DimensionNameIds::OVERWORLD, -64, 320, self::biome("minecraft:plains"), 128, true);
		self::$nether = Dimension::createVanilla(DimensionNameIds::NETHER, 0, 128, self::biome("minecraft:hell"));
		self::$theEnd = Dimension::createVanilla(DimensionNameIds::THE_END, 0, 256, self::biome("minecraft:the_end"));
		//TODO: The Sift - left for 2027!
	}

	private static function biome(string $name) : Biome{
		return BiomeTypeIdMap::getInstance()->fromName($name) ?? throw new AssumptionFailedError("Biome $name is not registered");
	}

	public static function OVERWORLD() : Dimension{
		if(!isset(self::$overworld)){
			self::init();
		}
		return self::$overworld;
	}

	public static function NETHER() : Dimension{
		if(!isset(self::$nether)){
			self::init();
		}
		return self::$nether;
	}

	public static function THE_END() : Dimension{
		if(!isset(self::$theEnd)){
			self::init();
		}
		return self::$theEnd;
	}

	/**
	 * @return Dimension[]
	 * @phpstan-return list<Dimension>
	 */
	public static function getAll() : array{
		return [
			self::OVERWORLD(),
			self::NETHER(),
			self::THE_END()
		];
	}
}
