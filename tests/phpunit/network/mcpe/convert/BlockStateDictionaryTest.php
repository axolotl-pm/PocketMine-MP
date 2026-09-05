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
	/** Known canonical hashes used to detect changes to Mojang's block-state hashing format. */
	private const CYAN_TERRACOTTA_NETWORK_ID_HASH = 973836165;
	private const BLUE_CANDLE_NETWORK_ID_HASH = 1088625327;
	private const MINECRAFT_UNKNOWN_NETWORK_ID_HASH = -2;

	public function testSequentialNetworkIdsRemainSupported() : void{
		$entry = new BlockStateDictionaryEntry("minecraft:cyan_terracotta", [], 0);
		$dictionary = new BlockStateDictionary([$entry]);

		self::assertFalse($dictionary->useBlockNetworkIdsHashes());
		self::assertSame(0, $dictionary->lookupStateIdFromData(BlockStateData::current("minecraft:cyan_terracotta", [])));
		self::assertSame("minecraft:cyan_terracotta", $dictionary->generateDataFromStateId(0)?->getName());
	}

	public function testHashedNetworkIds() : void{
		$entry = new BlockStateDictionaryEntry("minecraft:cyan_terracotta", [], 0);
		$dictionary = new BlockStateDictionary([$entry], true);

		self::assertTrue($dictionary->useBlockNetworkIdsHashes());
		self::assertSame(self::CYAN_TERRACOTTA_NETWORK_ID_HASH, $entry->getNetworkIdHash());
		self::assertSame(self::CYAN_TERRACOTTA_NETWORK_ID_HASH, $dictionary->lookupStateIdFromData(BlockStateData::current("minecraft:cyan_terracotta", [])));
		self::assertSame("minecraft:cyan_terracotta", $dictionary->generateDataFromStateId(self::CYAN_TERRACOTTA_NETWORK_ID_HASH)?->getName());
		self::assertNull($dictionary->generateDataFromStateId(0));
	}

	public function testHashUsesSortedStateProperties() : void{
		$entry = new BlockStateDictionaryEntry("minecraft:blue_candle", [
			"lit" => new ByteTag(0),
			"candles" => new IntTag(0)
		], 0);

		self::assertSame(self::BLUE_CANDLE_NETWORK_ID_HASH, $entry->getNetworkIdHash());
	}

	public function testUnknownBlockUsesReservedHash() : void{
		$entry = new BlockStateDictionaryEntry("minecraft:unknown", [], 0);
		$dictionary = new BlockStateDictionary([$entry], true);

		self::assertSame(self::MINECRAFT_UNKNOWN_NETWORK_ID_HASH, $entry->getNetworkIdHash());
		self::assertSame(self::MINECRAFT_UNKNOWN_NETWORK_ID_HASH, $dictionary->lookupStateIdFromData(BlockStateData::current("minecraft:unknown", [])));
		self::assertSame("minecraft:unknown", $dictionary->generateDataFromStateId(self::MINECRAFT_UNKNOWN_NETWORK_ID_HASH)?->getName());
	}
}
