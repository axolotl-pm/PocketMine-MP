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

namespace pocketmine\network\mcpe\convert;

use pocketmine\entity\InvalidSkinException;
use pocketmine\entity\Skin;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;
use pocketmine\utils\Filesystem;
use Symfony\Component\Filesystem\Path;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function random_bytes;
use function str_repeat;
use const JSON_THROW_ON_ERROR;

class LegacySkinAdapter implements SkinAdapter{
	private const DEFAULT_GEOMETRY_NAME = "geometry.humanoid.custom";

	/**
	 * Definitions of the vanilla player geometries, keyed by identifier. Clients reference these without
	 * ever shipping a definition for them, and both the wide (Steve) and the slim (Alex) variant are
	 * affected.
	 *
	 * @var array<string, string>|null
	 * @phpstan-var array<string, string>|null
	 */
	private static ?array $defaultGeometryData = null;

	/**
	 * Returns a standalone geometry document containing only the requested definition, so a skin never
	 * carries a geometry it doesn't reference.
	 */
	private static function defaultGeometryFor(string $geometryName) : ?string{
		if(self::$defaultGeometryData === null){
			$decoded = json_decode(Filesystem::fileGetContents(
				Path::join(\pocketmine\RESOURCE_PATH, "default_skin_geometry.json")
			), true);
			$formatVersion = is_array($decoded) && is_string($decoded["format_version"] ?? null) ? $decoded["format_version"] : "1.21.0";
			$geometries = is_array($decoded) && is_array($decoded["minecraft:geometry"] ?? null) ? $decoded["minecraft:geometry"] : [];

			$result = [];
			foreach($geometries as $geometry){
				$identifier = is_array($geometry) ? ($geometry["description"]["identifier"] ?? null) : null;
				if(!is_string($identifier)){
					continue;
				}
				$encoded = json_encode([
					"format_version" => $formatVersion,
					"minecraft:geometry" => [$geometry],
				]);
				if($encoded !== false){
					$result[$identifier] = $encoded;
				}
			}
			self::$defaultGeometryData = $result;
		}

		return self::$defaultGeometryData[$geometryName] ?? null;
	}

	public function toSkinData(Skin $skin) : SkinData{
		$capeData = $skin->getCapeData();
		$capeImage = $capeData === "" ? new SkinImage(0, 0, "") : new SkinImage(32, 64, $capeData);
		$geometryName = $skin->getGeometryName();
		if($geometryName === ""){
			$geometryName = self::DEFAULT_GEOMETRY_NAME;
		}
		$geometryData = $skin->getGeometryData();
		if($geometryData === ""){
			//the client drops the connection if it receives an empty geometry string, and since 1.26.40
			//it also drops it when the skin names a geometry it doesn't ship a definition for
			$geometryData = self::defaultGeometryFor($geometryName) ?? SkinData::GEOMETRY_DATA_NONE;
		}
		return new SkinData(
			$skin->getSkinId(),
			"", //TODO: playfab ID
			json_encode(["geometry" => ["default" => $geometryName]], JSON_THROW_ON_ERROR),
			SkinImage::fromLegacy($skin->getSkinData()),
			[],
			$capeImage,
			$geometryData
		);
	}

	public function fromSkinData(SkinData $data) : Skin{
		if($data->isPersona()){
			return new Skin("Standard_Custom", str_repeat(random_bytes(3) . "\xff", 4096));
		}

		$capeData = $data->isPersonaCapeOnClassic() ? "" : $data->getCapeImage()->getData();

		$resourcePatch = json_decode($data->getResourcePatch(), true);
		if(is_array($resourcePatch) && isset($resourcePatch["geometry"]["default"]) && is_string($resourcePatch["geometry"]["default"])){
			$geometryName = $resourcePatch["geometry"]["default"];
		}else{
			throw new InvalidSkinException("Missing geometry name field");
		}

		return new Skin($data->getSkinId(), $data->getSkinImage()->getData(), $capeData, $geometryName, $data->getGeometryDataJson());
	}
}
