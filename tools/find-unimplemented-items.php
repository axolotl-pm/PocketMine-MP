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

namespace pocketmine\tools\find_unimplemented_items;

use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\data\bedrock\item\BlockItemIdMap;
use pocketmine\data\bedrock\item\ItemDeserializer;
use pocketmine\data\bedrock\item\upgrade\ItemDataUpgrader;
use pocketmine\network\mcpe\convert\ItemTypeDictionaryFromDataHelper;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Filesystem;
use pocketmine\VersionInfo;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use function array_pad;
use function array_slice;
use function count;
use function dirname;
use function explode;
use function fwrite;
use function implode;
use function round;
use function sort;
use function str_repeat;
use function str_starts_with;
use function substr;
use const SORT_STRING;
use const STDERR;
use const STDOUT;

require_once dirname(__DIR__) . '/vendor/autoload.php';

const CLI_OPTIONS = [
	"item-list" => [
		"takesValue" => true,
		"description" => "path to the input required item list file (defaults to the one bundled with PocketMine-MP)"
	],
	"markdown" => [
		"takesValue" => false,
		"description" => "output unimplemented items as GitHub-flavoured markdown instead of the default log format"
	],
];

/**
 * @return string[]
 * @phpstan-return list<string>
 */
function getCandidateItemNames(ItemTypeDictionary $dictionary, BlockItemIdMap $blockItemIdMap, ItemDataUpgrader $upgrader) : array{
	$names = [];

	foreach($dictionary->getEntries() as $entry){
		$id = $entry->getStringId();
		if($id === "minecraft:air"){
			continue;
		}

		//TODO: HACK! Workaround for legacy BlockItem IDs in ItemRegistryPacket.
		//BlockItems received from the packet use legacy IDs for items that
		//have been remapped to flattened IDs. This workaround forces an
		//upgrade of all items, relying on a default metadata value of 0
		//to resolve the current ID and distinguish BlockItems.
		$itemData = $upgrader->upgradeItemTypeDataString($id, 0, 1, null)->getTypeData();
		if($blockItemIdMap->lookupBlockId($itemData->getName()) !== null){
			continue;
		}

		$names[] = $id;
	}

	return $names;
}

/**
 * @param string[] $itemNames
 * @phpstan-param list<string> $itemNames
 *
 * @return string[]
 * @phpstan-return list<string>
 */
function findUnimplementedItems(array $itemNames, ItemDeserializer $deserializer) : array{
	$unimplemented = [];

	foreach($itemNames as $itemName){
		if($deserializer->getDeserializerForId($itemName) === null){
			$unimplemented[] = $itemName;
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

	$itemListPath = (string) ($values["item-list"] ?? BedrockDataFiles::REQUIRED_ITEM_LIST_JSON);
	$markdown = isset($values["markdown"]);

	return [$markdown, $itemListPath];
}

/**
 * @param string[] $unimplemented
 * @phpstan-param list<string> $unimplemented
 */
function renderText(array $unimplemented, int $totalCandidates, float $percentage) : string{
	$lines = [];

	foreach($unimplemented as $itemName){
		$lines[] = "- $itemName";
	}

	$lines[] = str_repeat('-', 72);
	$lines[] = count($unimplemented) . " unimplemented items out of $totalCandidates candidates ($percentage%)";

	return implode("\n", $lines) . "\n";
}

/**
 * @param string[] $unimplemented
 * @phpstan-param list<string> $unimplemented
 */
function renderMarkdown(array $unimplemented, int $totalCandidates, float $percentage) : string{
	$lines = [];
	$lines[] = "> " . count($unimplemented) . " unimplemented items out of $totalCandidates candidates ($percentage%)";
	$lines[] = "";

	foreach($unimplemented as $itemName){
		$lines[] = "- `$itemName`";
	}

	return implode("\n", $lines) . "\n";
}

/**
 * @param string[] $argv
 */
function main(array $argv) : int{
	try{
		[$markdown, $itemListPath] = parseArgs($argv);
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
		$dictionary = ItemTypeDictionaryFromDataHelper::loadFromString(Filesystem::fileGetContents($itemListPath));
	}catch(\RuntimeException|AssumptionFailedError $e){
		fwrite(STDERR, "Failed to load item list file $itemListPath: " . $e->getMessage() . "\n");
		return 1;
	}

	$candidateNames = getCandidateItemNames($dictionary, BlockItemIdMap::getInstance(), GlobalItemDataHandlers::getUpgrader());
	$totalCandidates = count($candidateNames);
	if($totalCandidates === 0){
		fwrite(STDERR, "Item list contains no non-block item candidates\n");
		return 1;
	}

	$unimplemented = findUnimplementedItems($candidateNames, GlobalItemDataHandlers::getDeserializer());
	sort($unimplemented, SORT_STRING);
	$totalMissing = count($unimplemented);

	$percentage = round($totalMissing / $totalCandidates * 100, 1);

	fwrite(STDOUT, "Generated from " . VersionInfo::NAME . " " . VersionInfo::VERSION()->getFullVersion() . "\n");

	fwrite(STDOUT, $markdown
		? renderMarkdown($unimplemented, $totalCandidates, $percentage)
		: renderText($unimplemented, $totalCandidates, $percentage)
	);

	return 0;
}

exit(main($argv ?? []));
