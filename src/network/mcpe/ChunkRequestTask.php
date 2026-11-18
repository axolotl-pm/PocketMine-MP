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

namespace pocketmine\network\mcpe;

use pmmp\encoding\ByteBufferWriter;
use pmmp\thread\ThreadSafeArray;
use pocketmine\network\mcpe\compression\CompressBatchPromise;
use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\types\ChunkPosition;
use pocketmine\network\mcpe\serializer\ChunkSerializer;
use pocketmine\scheduler\AsyncTask;
use pocketmine\thread\NonThreadSafeValue;
use pocketmine\world\Dimension;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\format\SubChunk;
use function array_values;
use function chr;

class ChunkRequestTask extends AsyncTask{
	private const TLS_KEY_PROMISE = "promise";

	protected string $chunk;
	protected int $chunkX;
	protected int $chunkZ;
	private int $dimensionId;
	private int $minSubChunkIndex;
	private int $maxSubChunkIndex;
	private int $fallbackBiomeId;
	/** @phpstan-var ThreadSafeArray<int, int> */
	private ThreadSafeArray $knownBiomeIds;
	/** @phpstan-var NonThreadSafeValue<Compressor> */
	protected NonThreadSafeValue $compressor;
	private string $tiles;

	/**
	 * @param int[] $knownBiomeIds
	 * @phpstan-param list<int> $knownBiomeIds
	 */
	public function __construct(int $chunkX, int $chunkZ, Dimension $dimension, array $knownBiomeIds, Chunk $chunk, CompressBatchPromise $promise, Compressor $compressor){
		$this->compressor = new NonThreadSafeValue($compressor);
		$this->fallbackBiomeId = $dimension->getDefaultBiome()->getId();
		$this->knownBiomeIds = ThreadSafeArray::fromArray($knownBiomeIds);

		$this->chunk = FastChunkSerializer::serializeTerrain($chunk);
		$this->chunkX = $chunkX;
		$this->chunkZ = $chunkZ;
		$this->dimensionId = TypeConverter::getInstance()->getDimensionTranslator()->toNetworkId($dimension);
		$this->minSubChunkIndex = $dimension->getMinY() >> SubChunk::COORD_BIT_SIZE;
		$this->maxSubChunkIndex = ($dimension->getMaxY() >> SubChunk::COORD_BIT_SIZE) - 1;
		$this->tiles = ChunkSerializer::serializeTiles($chunk);

		$this->storeLocal(self::TLS_KEY_PROMISE, $promise);
	}

	public function onRun() : void{
		$chunk = FastChunkSerializer::deserializeTerrain($this->chunk);
		$dimensionId = $this->dimensionId;

		$subCount = ChunkSerializer::getSubChunkCount($chunk, $this->minSubChunkIndex, $this->maxSubChunkIndex);
		$converter = TypeConverter::getInstance();
		$payload = ChunkSerializer::serializeFullChunk(
			$chunk,
			$this->minSubChunkIndex,
			$this->maxSubChunkIndex,
			$converter->getBlockTranslator(),
			$this->fallbackBiomeId,
			array_values((array) $this->knownBiomeIds),
			$this->tiles
		);

		$stream = new ByteBufferWriter();
		PacketBatch::encodePackets($stream, [LevelChunkPacket::create(
			new ChunkPosition($this->chunkX, $this->chunkZ),
			$dimensionId, //@phpstan-ignore-line TODO: change this once we fix phpstan type of LevelChunkPacket
			$subCount,
			null,
			false,
			[],
			$payload,
			false //vanilla only sets this when the chunk's biomes were changed by the Script API
		)]);

		$compressor = $this->compressor->deserialize();
		$this->setResult(chr($compressor->getNetworkType()->value) . $compressor->compress($stream->getData()));
	}

	public function onCompletion() : void{
		/** @var CompressBatchPromise $promise */
		$promise = $this->fetchLocal(self::TLS_KEY_PROMISE);
		$promise->resolve($this->getResult());
	}
}
