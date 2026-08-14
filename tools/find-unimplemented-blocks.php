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

namespace pocketmine\tools\find_unimplemented_blocks;

use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\convert\BlockStateToObjectDeserializer;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\tag\Tag;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils;
use pocketmine\VersionInfo;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use function array_keys;
use function array_map;
use function array_pad;
use function array_slice;
use function count;
use function dirname;
use function explode;
use function fwrite;
use function hash;
use function implode;
use function ksort;
use function round;
use function str_repeat;
use function str_starts_with;
use function substr;
use const SORT_STRING;
use const STDERR;
use const STDOUT;

require_once dirname(__DIR__) . '/vendor/autoload.php';

const CLI_OPTIONS = [
	"palette" => [
		"takesValue" => true,
		"description" => "path to the input block palette file (defaults to the palette bundled with PocketMine-MP)"
	],
	"markdown" => [
		"takesValue" => false,
		"description" => "output unimplemented blocks as GitHub-flavoured markdown instead of the default log format"
	],
];

/**
 * Represents a single block's set of state properties.
 */
final class BlockStateSchema{

	/**
	 * @var bool[][]
	 * @phpstan-var array<string, array<string, Tag>>
	 * stateName => (hashedTag => Tag)
	 */
	private array $properties = [];

	public function __construct(
		public readonly string $blockName
	){}

	private function hashNBT(Tag $tag) : string{
		$encoded = (new LittleEndianNbtSerializer())->write(new TreeRoot($tag));
		return hash('sha256', $encoded, binary: true);
	}

	public function addStateValue(string $stateName, Tag $tag) : void{
		$this->properties[$stateName][$this->hashNBT($tag)] = $tag;
	}

	public function hasStates() : bool{
		return count($this->properties) !== 0;
	}

	/**
	 * @return Tag[][]
	 * @phpstan-return array<string, array<string, Tag>>
	 */
	public function getStateProperties() : array{
		$states = $this->properties;
		ksort($states, SORT_STRING);

		return $states;
	}
}

/**
 * @param BlockStateData[] $allStates
 * @phpstan-param list<BlockStateData> $allStates
 *
 * @return BlockStateSchema[]
 * @phpstan-return array<string, BlockStateSchema>
 */
function buildStateIndex(array $allStates) : array{
	$index = [];

	foreach($allStates as $stateData){
		$blockName = $stateData->getName();
		$definition = $index[$blockName] ??= new BlockStateSchema($blockName);
		foreach(Utils::stringifyKeys($stateData->getStates()) as $stateName => $tag){
			$definition->addStateValue($stateName, $tag);
		}
	}

	return $index;
}

/**
 * Returns the definitions matching the given block names, sorted naturally by block name.
 *
 * @param BlockStateSchema[] $index
 * @param string[]           $blockNames
 * @phpstan-param array<string, BlockStateSchema> $index
 * @phpstan-param list<string> $blockNames
 *
 * @return BlockStateSchema[]
 */
function filterAndSortDefinitions(array $index, array $blockNames) : array{
	$filtered = [];
	foreach($blockNames as $blockName){
		if(isset($index[$blockName])){
			$filtered[$blockName] = $index[$blockName];
		}
	}

	ksort($filtered, SORT_STRING);
	return $filtered;
}

/**
 * @param string[] $blockNames
 * @phpstan-param list<string> $blockNames
 *
 * @return string[]
 * @phpstan-return list<string>
 */
function findUnimplementedBlocks(array $blockNames, BlockStateToObjectDeserializer $deserializer) : array{
	$unimplemented = [];

	foreach($blockNames as $blockName){
		if($deserializer->getDeserializerForId($blockName) === null){
			$unimplemented[] = $blockName;
		}
	}

	return $unimplemented;
}

/**
 * @param string[] $argv
 *
 * @phpstan-return array{bool, string}
 *
 * @throws \InvalidArgumentException if an option doesn't exist
 * @throws \InvalidArgumentException if an option requiring a value is missing one
 * @throws \InvalidArgumentException if an option not taking a value is given one
 */
function parseArgs(array $argv) : array{
	$values = [];

	foreach(array_slice($argv, 1) as $arg){
		if(!str_starts_with($arg, '--')){
			throw new \InvalidArgumentException("Unknown option: $arg");
		}

		[$name, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, null);

		if(!isset(CLI_OPTIONS[$name])){
			throw new \InvalidArgumentException("Unknown option: $arg");
		}

		$takesValue = CLI_OPTIONS[$name]["takesValue"];
		if($takesValue && ($value === null || $value === '')){
			throw new \InvalidArgumentException("Missing value for --$name");
		}
		if(!$takesValue && $value !== null){
			throw new \InvalidArgumentException("Option --$name does not take a value");
		}

		$values[$name] = $value ?? true;
	}

	$palettePath = (string) ($values["palette"] ?? BedrockDataFiles::CANONICAL_BLOCK_STATES_NBT);
	$markdown = isset($values["markdown"]);

	return [$markdown, $palettePath];
}

/**
 * @param BlockStateSchema[] $unimplemented
 */
function renderText(array $unimplemented, int $uniqueBlocks, float $percentage) : string{
	$lines = [];

	foreach($unimplemented as $definition){
		$lines[] = "- BLOCK: " . $definition->blockName;
		if($definition->hasStates()){
			$lines[] = "  STATES:";
			foreach(Utils::stringifyKeys($definition->getStateProperties()) as $stateName => $tags){
				$formattedValues = implode(', ', array_map(
					static fn(Tag $tag) => $tag->toString(),
					$tags
				));
				$lines[] = "    " . $stateName . " => [" . $formattedValues . "]";
			}
		}
	}

	$lines[] = str_repeat('-', 72);
	$lines[] = "Summary: " . count($unimplemented) . " unimplemented blocks out of $uniqueBlocks in palette ($percentage%)";

	return implode("\n", $lines) . "\n";
}

/**
 * @param BlockStateSchema[] $unimplemented
 */
function renderMarkdown(array $unimplemented, int $uniqueBlocks, float $percentage) : string{
	$lines = [];
	$lines[] = "> " . count($unimplemented) . " unimplemented blocks out of $uniqueBlocks in palette ($percentage%)";
	$lines[] = "";

	foreach($unimplemented as $definition){
		if(!$definition->hasStates()){
			$lines[] = "";
			$lines[] = "`{$definition->blockName}`";
			$lines[] = "";
			continue;
		}
		$lines[] = "<details>";
		$lines[] = "<summary><code>{$definition->blockName}</code></summary>";
		$lines[] = "";
		$lines[] = "| State | Values |";
		$lines[] = "|-------|--------|";
		foreach(Utils::stringifyKeys($definition->getStateProperties()) as $stateName => $tags){
			$formattedValues = implode(', ', array_map(
				static fn(Tag $tag) => "`" . $tag->toString() . "`",
				$tags
			));
			$lines[] = "| `$stateName` | $formattedValues |";
		}
		$lines[] = "</details>";
	}

	return implode("\n", $lines) . "\n";
}

/**
 * @param string[] $argv
 */
function main(array $argv) : int{
	try{
		[$markdown, $palettePath] = parseArgs($argv);
	}catch(\InvalidArgumentException $e){
		fwrite(STDERR, $e->getMessage() . "\n");
		fwrite(STDERR, "Options:\n");
		foreach(CLI_OPTIONS as $_opt => $_meta){
			$_usage = $_meta["takesValue"] ? "--$_opt=<value>" : "--$_opt";
			fwrite(STDERR, "\t$_usage: {$_meta["description"]}\n");
		}
		return 1;
	}

	try{
		$allStates = BlockStateDictionary::loadPaletteFromString(Filesystem::fileGetContents($palettePath));
	}catch(\RuntimeException $e){
		fwrite(STDERR, "Failed to load block palette file $palettePath: " . $e->getMessage() . "\n");
		return 1;
	}

	$stateIndex = buildStateIndex($allStates);
	$uniqueBlocks = count($stateIndex);
	if($uniqueBlocks === 0){
		fwrite(STDERR, "Block palette is empty\n");
		return 1;
	}

	$unimplementedNames = findUnimplementedBlocks(array_keys($stateIndex), GlobalBlockStateHandlers::getDeserializer());
	$totalMissing = count($unimplementedNames);
	$unimplemented = filterAndSortDefinitions($stateIndex, $unimplementedNames);

	$displayPercentage = round($totalMissing / $uniqueBlocks * 100, 1);

	fwrite(STDOUT, "Generated from " . VersionInfo::NAME . " " . VersionInfo::VERSION()->getFullVersion() . "\n");

	fwrite(STDOUT, $markdown
		? renderMarkdown($unimplemented, $uniqueBlocks, $displayPercentage)
		: renderText($unimplemented, $uniqueBlocks, $displayPercentage)
	);

	return 0;
}

exit(main($argv ?? []));
