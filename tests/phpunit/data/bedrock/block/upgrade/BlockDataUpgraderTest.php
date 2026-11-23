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
use pocketmine\data\bedrock\block\BlockStateNames;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\VersionInfo;
use pocketmine\world\format\io\GlobalBlockStateHandlers;

class BlockDataUpgraderTest extends TestCase{

	private const PM_5_27_BLOCKSTATE_VERSION = (1 << 24) | (21 << 16) | (70 << 8) | 1;

	private static function invalidOakFenceState() : CompoundTag{
		return CompoundTag::create()
			->setString("name", BlockTypeNames::OAK_FENCE)
			->setTag("states", CompoundTag::create())
			->setInt("version", self::PM_5_27_BLOCKSTATE_VERSION);
	}

	public function testBogusDataUpgraded() : void{
		$tag = self::invalidOakFenceState()->setLong(VersionInfo::TAG_WORLD_DATA_VERSION, 1);

		$result = GlobalBlockStateHandlers::getUpgrader()->upgradeBlockStateNbt($tag);

		self::assertArrayHasKey(BlockStateNames::MC_CONNECTION_EAST, $result->getStates());
		GlobalBlockStateHandlers::getDeserializer()->deserialize($result);
	}

	public function testNotUpgradeForMissingWorldDataVersionTag() : void{
		$result = GlobalBlockStateHandlers::getUpgrader()->upgradeBlockStateNbt(self::invalidOakFenceState());

		self::assertArrayNotHasKey(BlockStateNames::MC_CONNECTION_EAST, $result->getStates());
	}
}
