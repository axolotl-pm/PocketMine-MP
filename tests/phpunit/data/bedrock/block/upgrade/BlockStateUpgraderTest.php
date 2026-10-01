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

namespace pocketmine\data\bedrock\block\upgrade;

use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils;
use Symfony\Component\Filesystem\Path;
use function array_keys;
use function array_slice;
use function basename;
use function count;
use function file_exists;
use function glob;
use function implode;
use function ksort;
use function sprintf;
use function str_starts_with;
use const PHP_INT_MAX;
use const pocketmine\BEDROCK_BLOCK_UPGRADE_SCHEMA_PATH;
use const pocketmine\PATH;
use const SORT_STRING;

class BlockStateUpgraderTest extends TestCase{

	private const TEST_BLOCK = "pocketmine:test_block";
	private const TEST_BLOCK_2 = "pocketmine:test_block_2";
	private const TEST_PROPERTY = "test_property";
	private const TEST_PROPERTY_2 = "test_property_2";
	private const TEST_VERSION = 1;

	private const TEST_PROPERTY_VALUE_1 = 1;
	private const TEST_PROPERTY_VALUE_2 = 2;
	private const TEST_PROPERTY_VALUE_3 = 3;

	private BlockStateUpgrader $upgrader;

	public function setUp() : void{
		$this->upgrader = new BlockStateUpgrader([]);
	}

	private function getNewSchema() : BlockStateUpgradeSchema{
		return $this->getNewSchemaVersion(PHP_INT_MAX, 0);
	}

	private function getNewSchemaVersion(int $versionId, int $schemaId) : BlockStateUpgradeSchema{
		$schema = new BlockStateUpgradeSchema(($versionId >> 24) & 0xff, ($versionId >> 16) & 0xff, ($versionId >> 8) & 0xff, $versionId & 0xff, $schemaId);
		$this->upgrader->addSchema($schema);
		return $schema;
	}

	/**
	 * @phpstan-param \Closure() : BlockStateData $getStateData
	 */
	private function upgrade(BlockStateData $stateData, \Closure $getStateData) : BlockStateData{
		$result = $this->upgrader->upgrade($stateData);
		self::assertTrue($stateData->equals($getStateData()), "Upgrading states must not alter the original input");

		return $result;
	}

	public function testRenameId() : void{
		$this->getNewSchema()->renamedIds[self::TEST_BLOCK] = self::TEST_BLOCK_2;

		$getStateData = fn() => self::getEmptyPreimage();
		$upgradedStateData = $this->upgrade($getStateData(), $getStateData);

		self::assertSame($upgradedStateData->getName(), self::TEST_BLOCK_2);
	}

	private function prepareAddPropertySchema(BlockStateUpgradeSchema $schema) : void{
		$schema->addedProperties[self::TEST_BLOCK][self::TEST_PROPERTY] = new IntTag(self::TEST_PROPERTY_VALUE_1);
	}

	private static function getEmptyPreimage() : BlockStateData{
		return new BlockStateData(self::TEST_BLOCK, [], self::TEST_VERSION);
	}

	private static function getPreimageOneProperty(string $propertyName, int $value) : BlockStateData{
		return new BlockStateData(
			self::TEST_BLOCK,
			[$propertyName => new IntTag($value)],
			self::TEST_VERSION
		);
	}

	public function testAddNewProperty() : void{
		$this->prepareAddPropertySchema($this->getNewSchema());

		$getStateData = fn() => self::getEmptyPreimage();
		$upgradedStateData = $this->upgrade($getStateData(), $getStateData);

		self::assertSame(self::TEST_PROPERTY_VALUE_1, $upgradedStateData->getState(self::TEST_PROPERTY)?->getValue());
	}

	public function testAddPropertyAlreadyExists() : void{
		$this->prepareAddPropertySchema($this->getNewSchema());

		$getStateData = fn() => self::getPreimageOneProperty(self::TEST_PROPERTY, self::TEST_PROPERTY_VALUE_1 + 1);
		$stateData = $getStateData();
		$upgradedStateData = $this->upgrade($stateData, $getStateData);

		//the object may not be the same due to
		self::assertTrue($stateData->equals($upgradedStateData), "Adding a property that already exists with a different value should not alter the state");
	}

	private function prepareRemovePropertySchema(BlockStateUpgradeSchema $schema) : void{
		$schema->removedProperties[self::TEST_BLOCK][] = self::TEST_PROPERTY;
	}

	/**
	 * @phpstan-return \Generator<int, array{\Closure() : BlockStateData}, void, void>
	 */
	public static function removePropertyProvider() : \Generator{
		yield [fn() => self::getEmptyPreimage()];
		yield [fn() => self::getPreimageOneProperty(self::TEST_PROPERTY, self::TEST_PROPERTY_VALUE_1)];
	}

	/**
	 * @dataProvider removePropertyProvider
	 * @phpstan-param \Closure() : BlockStateData $getStateData
	 */
	public function testRemoveProperty(\Closure $getStateData) : void{
		$this->prepareRemovePropertySchema($this->getNewSchema());

		$upgradedStateData = $this->upgrade($getStateData(), $getStateData);

		self::assertNull($upgradedStateData->getState(self::TEST_PROPERTY));
	}

	private function prepareRenamePropertySchema(BlockStateUpgradeSchema $schema) : void{
		$schema->renamedProperties[self::TEST_BLOCK][self::TEST_PROPERTY] = self::TEST_PROPERTY_2;
	}

	/**
	 * @phpstan-return \Generator<int, array{\Closure() : BlockStateData, ?int}, void, void>
	 */
	public static function renamePropertyProvider() : \Generator{
		yield [fn() => self::getEmptyPreimage(), null];
		yield [fn() => self::getPreimageOneProperty(self::TEST_PROPERTY, self::TEST_PROPERTY_VALUE_1), self::TEST_PROPERTY_VALUE_1];
		yield [fn() => self::getPreimageOneProperty(self::TEST_PROPERTY_2, self::TEST_PROPERTY_VALUE_1), self::TEST_PROPERTY_VALUE_1];
	}

	/**
	 * @dataProvider renamePropertyProvider
	 * @phpstan-param \Closure() : BlockStateData $getStateData
	 */
	public function testRenameProperty(\Closure $getStateData, ?int $valueAfter) : void{
		$this->prepareRenamePropertySchema($this->getNewSchema());

		$upgradedStateData = $this->upgrade($getStateData(), $getStateData);

		self::assertSame($valueAfter, $upgradedStateData->getState(self::TEST_PROPERTY_2)?->getValue());
	}

	private function prepareRemapPropertyValueSchema(BlockStateUpgradeSchema $schema) : void{
		$schema->remappedPropertyValues[self::TEST_BLOCK][self::TEST_PROPERTY][] = new BlockStateUpgradeSchemaValueRemap(
			new IntTag(self::TEST_PROPERTY_VALUE_1),
			new IntTag(self::TEST_PROPERTY_VALUE_2)
		);
	}

	/**
	 * @phpstan-return \Generator<int, array{\Closure() : BlockStateData, ?int}, void, void>
	 */
	public static function remapPropertyValueProvider() : \Generator{
		//no property to remap
		yield [fn() => self::getEmptyPreimage(), null];

		//value that will be remapped
		yield [fn() => self::getPreimageOneProperty(self::TEST_PROPERTY, self::TEST_PROPERTY_VALUE_1), self::TEST_PROPERTY_VALUE_2];

		//value that is already at the target value
		yield [fn() => self::getPreimageOneProperty(self::TEST_PROPERTY, self::TEST_PROPERTY_VALUE_2), self::TEST_PROPERTY_VALUE_2];

		//value that is not remapped and is different from target value (to detect unconditional overwrite bugs)
		yield [fn() => self::getPreimageOneProperty(self::TEST_PROPERTY, self::TEST_PROPERTY_VALUE_3), self::TEST_PROPERTY_VALUE_3];
	}

	/**
	 * @dataProvider remapPropertyValueProvider
	 * @phpstan-param \Closure() : BlockStateData $getStateData
	 */
	public function testRemapPropertyValue(\Closure $getStateData, ?int $valueAfter) : void{
		$this->prepareRemapPropertyValueSchema($this->getNewSchema());

		$upgradedStateData = $this->upgrade($getStateData(), $getStateData);

		self::assertSame($upgradedStateData->getState(self::TEST_PROPERTY)?->getValue(), $valueAfter);
	}

	/**
	 * @dataProvider remapPropertyValueProvider
	 * @phpstan-param \Closure() : BlockStateData $getStateData
	 */
	public function testRemapAndRenameProperty(\Closure $getStateData, ?int $valueAfter) : void{
		$schema = $this->getNewSchema();
		$this->prepareRenamePropertySchema($schema);
		$this->prepareRemapPropertyValueSchema($schema);

		$upgradedStateData = $this->upgrade($getStateData(), $getStateData);

		self::assertSame($upgradedStateData->getState(self::TEST_PROPERTY_2)?->getValue(), $valueAfter);
	}

	public function testFlattenProperty() : void{
		$schema = $this->getNewSchema();
		$schema->flattenedProperties[self::TEST_BLOCK] = new BlockStateUpgradeSchemaFlattenInfo(
			"minecraft:",
			"test",
			"_suffix",
			[],
			StringTag::class
		);

		$stateData = new BlockStateData(self::TEST_BLOCK, ["test" => new StringTag("value1")], 0);
		$upgradedStateData = $this->upgrade($stateData, fn() => $stateData);

		self::assertSame("minecraft:value1_suffix", $upgradedStateData->getName());
		self::assertEmpty($upgradedStateData->getStates());
	}

	/**
	 * @phpstan-return \Generator<int, array{int, int, bool, int}, void, void>
	 */
	public static function upgraderVersionCompatibilityProvider() : \Generator{
		yield [0x1_00_00_00, 0x1_00_00_00, true, 2]; //Same version, multiple schemas targeting version - must be altered, we don't know which schemas are applicable
		yield [0x1_00_00_00, 0x1_00_00_00, false, 1]; //Same version, one schema targeting version - do not change
		yield [0x1_00_01_00, 0x1_00_00_00, true, 1]; //Schema newer than block: must be altered
		yield [0x1_00_00_00, 0x1_00_01_00, false, 1]; //Block newer than schema: block must NOT be altered
	}

	/**
	 * @dataProvider upgraderVersionCompatibilityProvider
	 */
	public function testUpgraderVersionCompatibility(int $schemaVersion, int $stateVersion, bool $shouldChange, int $schemaCount) : void{
		for($i = 0; $i < $schemaCount; $i++){
			$schema = $this->getNewSchemaVersion($schemaVersion, $i);
			$schema->renamedIds[self::TEST_BLOCK] = self::TEST_BLOCK_2;
		}

		$getStateData = fn() => new BlockStateData(
			self::TEST_BLOCK,
			[],
			$stateVersion
		);

		$upgradedStateData = $this->upgrade($getStateData(), $getStateData);
		$originalStateData = $getStateData();

		self::assertNotSame($shouldChange, $upgradedStateData->equals($originalStateData));
	}

	private static function paletteArchivePath() : string{
		$path = Path::join(PATH, 'vendor', 'axolotl-pm', 'bedrock-block-palette-archive');
		self::assertDirectoryExists($path, "BedrockBlockPaletteArchive is not installed at $path");
		return $path;
	}

	/**
	 * @return string[]
	 * @phpstan-return list<string>
	 */
	private static function paletteFiles(string $archiveDir) : array{
		$files = glob(Path::join($archiveDir, '*.nbt'));
		self::assertNotFalse($files, "failed to list palettes in $archiveDir");
		return $files;
	}

	private static function currentVersion(string $archiveDir) : string{
		$version = ProtocolInfo::MINECRAFT_VERSION_NETWORK;
		if(!file_exists(Path::join($archiveDir, $version . '.nbt'))){
			foreach(self::paletteFiles($archiveDir) as $file){
				if(str_starts_with(basename($file), $version . '.')){
					return basename($file, '.nbt');
				}
			}
		}
		return $version;
	}

	private static function canonical(BlockStateData $state) : string{
		$states = $state->getStates();
		ksort($states, SORT_STRING);
		$properties = [];
		foreach(Utils::stringifyKeys($states) as $name => $tag){
			$properties[] = $name . "=" . $tag;
		}
		return $state->getName() . "[" . implode(",", $properties) . "]";
	}

	/**
	 * @param true[][][] $currentProperties
	 * @phpstan-param array<string, array<string, array<string, true>>> $currentProperties
	 */
	private static function describeMismatch(BlockStateData $upgraded, array $currentProperties) : string{
		$expected = $currentProperties[$upgraded->getName()] ?? null;
		if($expected === null){
			return "block does not exist in the current palette";
		}

		$problems = [];
		foreach(Utils::stringifyKeys($upgraded->getStates()) as $property => $value){
			if(!isset($expected[$property])){
				$problems[] = "unexpected property " . $property;
			}elseif(!isset($expected[$property][(string) $value])){
				$problems[] = "invalid value " . $property . "=" . $value . " (expected " . implode("|", array_keys($expected[$property])) . ")";
			}
		}
		foreach(Utils::stringifyKeys($expected) as $property => $values){
			if($upgraded->getState($property) === null){
				$problems[] = "missing property " . $property . " (" . implode("|", array_keys($values)) . ")";
			}
		}

		return count($problems) === 0 ? "no current state has this combination of property values" : implode(", ", $problems);
	}

	public function testEveryArchivedStateUpgradesToACurrentState() : void{
		$archiveDir = self::paletteArchivePath();

		$schemaDir = Path::join(BEDROCK_BLOCK_UPGRADE_SCHEMA_PATH, 'nbt_upgrade_schema');
		$upgrader = new BlockStateUpgrader(BlockStateUpgradeSchemaUtils::loadSchemas($schemaDir, PHP_INT_MAX));

		$currentVersion = self::currentVersion($archiveDir);
		$targetFile = Path::join($archiveDir, $currentVersion . '.nbt');
		self::assertFileExists($targetFile, "palette archive has no palette for the current version ($currentVersion)");

		$current = [];
		/**
		 * @var true[][][] $currentProperties
		 * @phpstan-var array<string, array<string, array<string, true>>> $currentProperties
		 */
		$currentProperties = [];
		foreach(BlockStateDictionary::loadPaletteFromString(Filesystem::fileGetContents($targetFile)) as $state){
			$current[self::canonical($state)] = true;
			$currentProperties[$state->getName()] ??= [];
			foreach(Utils::stringifyKeys($state->getStates()) as $property => $value){
				$currentProperties[$state->getName()][$property][(string) $value] = true;
			}
		}

		$oldStates = [];
		foreach(self::paletteFiles($archiveDir) as $file){
			if(basename($file) === $currentVersion . '.nbt'){
				continue;
			}
			foreach(BlockStateDictionary::loadPaletteFromString(Filesystem::fileGetContents($file)) as $state){
				$oldStates[self::canonical($state)] = new BlockStateData($state->getName(), $state->getStates(), 0);
			}
		}

		$failures = [];
		foreach(Utils::stringifyKeys($oldStates) as $key => $forced){
			$upgraded = $upgrader->upgrade($forced);
			$upgradedKey = self::canonical($upgraded);
			if(!isset($current[$upgradedKey])){
				$failures[] = ($upgradedKey === $key ? $key : $key . ' -> ' . $upgradedKey) . ': ' . self::describeMismatch($upgraded, $currentProperties);
			}
		}

		self::assertSame([], $failures, sprintf(
			"%d block state(s) do not upgrade to a valid %s state (missing/incomplete upgrade schema):\n%s",
			count($failures),
			$currentVersion,
			implode("\n", array_slice($failures, 0, 40))
		));
	}
}
