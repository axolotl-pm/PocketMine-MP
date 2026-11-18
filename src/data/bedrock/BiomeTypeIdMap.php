<?php

declare(strict_types=1);

namespace pocketmine\data\bedrock;

use pocketmine\color\Color;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\utils\Filesystem;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use pocketmine\world\biome\Biome;
use pocketmine\world\biome\BiomeDefinitionData;
use pocketmine\world\biome\model\BiomeDefinitionEntryData;
use function array_keys;
use function array_values;
use function get_debug_type;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;

final class BiomeTypeIdMap{
	use SingletonTrait;
	/** @phpstan-use IntSaveIdMapTrait<Biome> */
	use IntSaveIdMapTrait {
		register as private registerId;
	}

	/**
	 * @var Biome[]
	 * @phpstan-var array<string, Biome>
	 */
	private array $nameToBiome = [];

	private bool $locked = false;

	private function __construct(){
		$idMap = self::loadIdMap(BedrockDataFiles::BIOME_ID_MAP_JSON);
		foreach(Utils::stringifyKeys(self::loadDefinitions(BedrockDataFiles::BIOME_DEFINITIONS_JSON)) as $name => $definition){
			$id = $idMap[$name] ?? throw new SavedDataLoadingException("No ID mapping found for biome $name");
			$this->register(new Biome($id, $name, $definition));
		}
	}

	/**
	 * @phpstan-return array<string, int>
	 */
	private static function loadIdMap(string $filePath) : array{
		$rawIdMap = json_decode(Filesystem::fileGetContents($filePath), associative: true);
		if(!is_array($rawIdMap)){
			throw new SavedDataLoadingException("$filePath root should be an array, got " . get_debug_type($rawIdMap));
		}

		$idMap = [];
		foreach(Utils::promoteKeys($rawIdMap) as $name => $id){
			if(!is_string($name) || !is_int($id)){
				throw new SavedDataLoadingException("$filePath should have string keys and int values");
			}
			$idMap["minecraft:$name"] = $id;
		}
		return $idMap;
	}

	/**
	 * @phpstan-return array<string, BiomeDefinitionData>
	 */
	private static function loadDefinitions(string $filePath) : array{
		$biomeEntries = json_decode(Filesystem::fileGetContents($filePath), associative: true);
		if(!is_array($biomeEntries)){
			throw new SavedDataLoadingException("$filePath root should be an array, got " . get_debug_type($biomeEntries));
		}

		$jsonMapper = new \JsonMapper();
		$jsonMapper->bExceptionOnMissingData = true;
		$jsonMapper->bStrictObjectTypeChecking = true;
		$jsonMapper->bEnforceMapType = false;

		$definitions = [];
		foreach(Utils::promoteKeys($biomeEntries) as $biomeName => $entry){
			if(!is_array($entry)){
				throw new SavedDataLoadingException("$filePath should be an array of objects, got " . get_debug_type($entry));
			}

			try{
				$model = $jsonMapper->map($entry, new BiomeDefinitionEntryData());
			}catch(\JsonMapper_Exception $e){
				throw new \RuntimeException($e->getMessage(), 0, $e);
			}

			$colour = $model->mapWaterColour;
			$definitions[(string) $biomeName] = new BiomeDefinitionData(
				$model->id,
				$model->temperature,
				$model->downfall,
				$model->foliageSnow,
				$model->depth,
				$model->scale,
				new Color($colour->r, $colour->g, $colour->b, $colour->a),
				$model->rain,
				$model->tags
			);
		}

		return $definitions;
	}

	public function register(Biome $biome) : void{
		if($this->locked){
			throw new \LogicException("Biomes cannot be registered after the first player has joined");
		}
		$id = $biome->getId();
		$name = $biome->getName();
		if($this->fromId($id) !== null){
			throw new \InvalidArgumentException("Biome ID $id is already registered");
		}
		if(isset($this->nameToBiome[$name])){
			throw new \InvalidArgumentException("Biome name $name is already registered");
		}

		$this->registerId($id, $biome);
		$this->nameToBiome[$name] = $biome;
	}

	public function fromName(string $name) : ?Biome{
		return $this->nameToBiome[$name] ?? null;
	}

	/**
	 * @return Biome[]
	 * @phpstan-return list<Biome>
	 */
	public function getAll() : array{
		return array_values($this->nameToBiome);
	}

	/**
	 * @return int[]
	 * @phpstan-return list<int>
	 */
	public function getIds() : array{
		return array_keys($this->idToEnum);
	}

	/**
	 * @internal
	 */
	public function lock() : void{
		$this->locked = true;
	}
}
