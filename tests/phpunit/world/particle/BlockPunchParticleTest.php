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

namespace pocketmine\world\particle;

use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;

class BlockPunchParticleTest extends TestCase{

	public function tearDown() : void{
		TypeConverter::reset();
	}

	public function testSequentialNetworkIds() : void{
		$typeConverter = new TypeConverter(false);
		TypeConverter::setInstance($typeConverter);
		$block = VanillaBlocks::STONE();
		$networkId = $typeConverter->getBlockTranslator()->internalIdToNetworkId($block->getStateId());

		foreach(self::getFaces() as $face => $eventId){
			$packet = self::encodeParticle($block, $face);
			self::assertSame(LevelEvent::PARTICLE_PUNCH_BLOCK, $packet->eventId);
			self::assertSame($networkId | ($face << 24), $packet->eventData);
		}
	}

	public function testHashedNetworkIds() : void{
		$typeConverter = new TypeConverter(true);
		TypeConverter::setInstance($typeConverter);
		$block = VanillaBlocks::STONE();
		$networkId = $typeConverter->getBlockTranslator()->internalIdToNetworkId($block->getStateId());

		foreach(self::getFaces() as $face => $eventId){
			$packet = self::encodeParticle($block, $face);
			self::assertSame($eventId, $packet->eventId);
			self::assertSame($networkId, $packet->eventData);
		}
	}

	/**
	 * @return array<int, int>
	 */
	private static function getFaces() : array{
		return [
			Facing::DOWN => LevelEvent::PARTICLE_PUNCH_BLOCK_DOWN,
			Facing::UP => LevelEvent::PARTICLE_PUNCH_BLOCK_UP,
			Facing::NORTH => LevelEvent::PARTICLE_PUNCH_BLOCK_NORTH,
			Facing::SOUTH => LevelEvent::PARTICLE_PUNCH_BLOCK_SOUTH,
			Facing::WEST => LevelEvent::PARTICLE_PUNCH_BLOCK_WEST,
			Facing::EAST => LevelEvent::PARTICLE_PUNCH_BLOCK_EAST
		];
	}

	private static function encodeParticle(Block $block, int $face) : LevelEventPacket{
		$packets = (new BlockPunchParticle($block, $face))->encode(new Vector3(0, 0, 0));
		self::assertCount(1, $packets);
		$packet = $packets[0];
		self::assertInstanceOf(LevelEventPacket::class, $packet);
		return $packet;
	}
}
