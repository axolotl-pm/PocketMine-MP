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

namespace pocketmine\block;

use PHPUnit\Framework\TestCase;
use pocketmine\block\utils\SupportType;
use pocketmine\math\Facing;

class PartialHeightDirtSupportTest extends TestCase{

	/**
	 * @return \Generator<int, array{Block}, void, void>
	 */
	public static function partialHeightDirtProvider() : \Generator{
		yield [VanillaBlocks::FARMLAND()];
		yield [VanillaBlocks::GRASS_PATH()];
	}

	/**
	 * @dataProvider partialHeightDirtProvider
	 */
	public function testPartialHeightDirtSupportTypes(Block $block) : void{
		self::assertSame(SupportType::EDGE, $block->getSupportType(Facing::UP));
		self::assertSame(SupportType::FULL, $block->getSupportType(Facing::DOWN));
		self::assertSame(SupportType::NONE, $block->getSupportType(Facing::NORTH));
		self::assertSame(SupportType::NONE, $block->getSupportType(Facing::SOUTH));
		self::assertSame(SupportType::NONE, $block->getSupportType(Facing::EAST));
		self::assertSame(SupportType::NONE, $block->getSupportType(Facing::WEST));
	}

	public function testEdgeSupportAllowsRedstoneWireButNotTorches() : void{
		$edge = SupportType::EDGE;
		self::assertTrue($edge->hasEdgeSupport());
		self::assertFalse($edge->hasCenterSupport());

		$none = SupportType::NONE;
		self::assertFalse($none->hasEdgeSupport());
		self::assertFalse($none->hasCenterSupport());

		$center = SupportType::CENTER;
		self::assertFalse($center->hasEdgeSupport());
		self::assertTrue($center->hasCenterSupport());
	}
}
