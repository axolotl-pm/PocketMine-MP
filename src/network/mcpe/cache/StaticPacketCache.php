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

namespace pocketmine\network\mcpe\cache;

use pocketmine\color\Color;
use pocketmine\data\bedrock\ArmorTrimMaterialTypeIdMap;
use pocketmine\data\bedrock\ArmorTrimPatternTypeIdMap;
use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\item\ArmorTrimMaterial;
use pocketmine\item\ArmorTrimPattern;
use pocketmine\network\mcpe\protocol\AvailableActorIdentifiersPacket;
use pocketmine\network\mcpe\protocol\BiomeDefinitionListPacket;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\network\mcpe\protocol\TrimDataPacket;
use pocketmine\network\mcpe\protocol\types\biome\BiomeDefinitionEntry;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\TrimMaterial;
use pocketmine\network\mcpe\protocol\types\TrimPattern;
use pocketmine\utils\Filesystem;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use pocketmine\world\biome\model\BiomeDefinitionEntryData;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use function count;
use function get_debug_type;
use function is_array;
use function json_decode;

class StaticPacketCache{
	use SingletonTrait;

	/**
	 * @phpstan-return CacheableNbt<\pocketmine\nbt\tag\CompoundTag>
	 */
	private static function loadCompoundFromFile(string $filePath) : CacheableNbt{
		return new CacheableNbt((new NetworkNbtSerializer())->read(Filesystem::fileGetContents($filePath))->mustGetCompoundTag());
	}

	/**
	 * @return list<BiomeDefinitionEntry>
	 */
	private static function loadBiomeDefinitionModel(string $filePath) : array{
		$biomeEntries = json_decode(Filesystem::fileGetContents($filePath), associative: true);
		if(!is_array($biomeEntries)){
			throw new SavedDataLoadingException("$filePath root should be an array, got " . get_debug_type($biomeEntries));
		}

		$jsonMapper = new \JsonMapper();
		$jsonMapper->bExceptionOnMissingData = true;
		$jsonMapper->bStrictObjectTypeChecking = true;
		$jsonMapper->bEnforceMapType = false;

		$entries = [];
		foreach(Utils::promoteKeys($biomeEntries) as $biomeName => $entry){
			if(!is_array($entry)){
				throw new SavedDataLoadingException("$filePath should be an array of objects, got " . get_debug_type($entry));
			}

			try{
				$biomeDefinition = $jsonMapper->map($entry, new BiomeDefinitionEntryData());

				$mapWaterColour = $biomeDefinition->mapWaterColour;
				$entries[] = new BiomeDefinitionEntry(
					(string) $biomeName,
					$biomeDefinition->id,
					$biomeDefinition->temperature,
					$biomeDefinition->downfall,
					$biomeDefinition->foliageSnow,
					$biomeDefinition->depth,
					$biomeDefinition->scale,
					new Color(
						$mapWaterColour->r,
						$mapWaterColour->g,
						$mapWaterColour->b,
						$mapWaterColour->a
					),
					$biomeDefinition->rain,
					count($biomeDefinition->tags) > 0 ? $biomeDefinition->tags : null,
				);
			}catch(\JsonMapper_Exception $e){
				throw new \RuntimeException($e->getMessage(), 0, $e);
			}
		}

		return $entries;
	}

	private static function makeTrimData() : TrimDataPacket{
		$itemSerializer = GlobalItemDataHandlers::getSerializer();
		$patternIdMap = ArmorTrimPatternTypeIdMap::getInstance();
		$materialIdMap = ArmorTrimMaterialTypeIdMap::getInstance();

		$patterns = [];
		foreach(ArmorTrimPattern::cases() as $pattern){
			$patterns[] = new TrimPattern(
				$itemSerializer->serializeType($pattern->getTemplate())->getName(),
				$patternIdMap->toId($pattern)
			);
		}
		$materials = [];
		foreach(ArmorTrimMaterial::cases() as $material){
			$materials[] = new TrimMaterial(
				$materialIdMap->toId($material),
				$material->getColor(),
				$itemSerializer->serializeType($material->getItem())->getName()
			);
		}

		return TrimDataPacket::create($patterns, $materials);
	}

	private static function make() : self{
		return new self(
			BiomeDefinitionListPacket::fromDefinitions(self::loadBiomeDefinitionModel(BedrockDataFiles::BIOME_DEFINITIONS_JSON)),
			AvailableActorIdentifiersPacket::create(self::loadCompoundFromFile(BedrockDataFiles::ENTITY_IDENTIFIERS_NBT)),
			self::makeTrimData()
		);
	}

	public function __construct(
		private BiomeDefinitionListPacket $biomeDefs,
		private AvailableActorIdentifiersPacket $availableActorIdentifiers,
		private TrimDataPacket $trimData
	){}

	public function getTrimData() : TrimDataPacket{
		return $this->trimData;
	}

	public function getBiomeDefs() : BiomeDefinitionListPacket{
		return $this->biomeDefs;
	}

	public function getAvailableActorIdentifiers() : AvailableActorIdentifiersPacket{
		return $this->availableActorIdentifiers;
	}
}
