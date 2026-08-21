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

use PHPUnit\Framework\TestCase;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\IntTag;

class BlockStateDictionaryTest extends TestCase{

	public function testSequentialNetworkIdsRemainSupported() : void{
		$entry = new BlockStateDictionaryEntry("minecraft:cyan_terracotta", [], 0);
		$dictionary = new BlockStateDictionary([$entry]);

		self::assertFalse($dictionary->networkIdsAreHashes());
		self::assertSame(0, $dictionary->lookupStateIdFromData(BlockStateData::current("minecraft:cyan_terracotta", [])));
		self::assertSame("minecraft:cyan_terracotta", $dictionary->generateDataFromStateId(0)?->getName());
	}

	public function testHashedNetworkIds() : void{
		$entry = new BlockStateDictionaryEntry("minecraft:cyan_terracotta", [], 0);
		$dictionary = new BlockStateDictionary([$entry], true);

		self::assertTrue($dictionary->networkIdsAreHashes());
		self::assertSame(973836165, $entry->getNetworkIdHash());
		self::assertSame(973836165, $dictionary->lookupStateIdFromData(BlockStateData::current("minecraft:cyan_terracotta", [])));
		self::assertSame("minecraft:cyan_terracotta", $dictionary->generateDataFromStateId(973836165)?->getName());
		self::assertNull($dictionary->generateDataFromStateId(0));
	}

	public function testHashUsesSortedStateProperties() : void{
		$entry = new BlockStateDictionaryEntry("minecraft:blue_candle", [
			"lit" => new ByteTag(0),
			"candles" => new IntTag(0)
		], 0);

		self::assertSame(1088625327, $entry->getNetworkIdHash());
	}

	public function testUnknownBlockUsesReservedHash() : void{
		$entry = new BlockStateDictionaryEntry("minecraft:unknown", [], 0);
		$dictionary = new BlockStateDictionary([$entry], true);

		self::assertSame(-2, $entry->getNetworkIdHash());
		self::assertSame(-2, $dictionary->lookupStateIdFromData(BlockStateData::current("minecraft:unknown", [])));
		self::assertSame("minecraft:unknown", $dictionary->generateDataFromStateId(-2)?->getName());
	}
}
