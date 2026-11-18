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

namespace pocketmine\world\generator\biome;

use pocketmine\block\Block;
use pocketmine\utils\Random;
use pocketmine\world\biome\Biome;
use pocketmine\world\ChunkManager;
use pocketmine\world\generator\populator\Populator;

final class GeneratorBiome{

	/**
	 * @param Block[]     $groundCover
	 * @param Populator[] $populators
	 * @phpstan-param list<Block>     $groundCover
	 * @phpstan-param list<Populator> $populators
	 */
	public function __construct(
		private readonly Biome $biome,
		private readonly int $minElevation,
		private readonly int $maxElevation,
		private readonly array $groundCover = [],
		private readonly array $populators = []
	){}

	public function getBiome() : Biome{
		return $this->biome;
	}

	public function getMinElevation() : int{
		return $this->minElevation;
	}

	public function getMaxElevation() : int{
		return $this->maxElevation;
	}

	/**
	 * @return Block[]
	 * @phpstan-return list<Block>
	 */
	public function getGroundCover() : array{
		return $this->groundCover;
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		foreach($this->populators as $populator){
			$populator->populate($world, $chunkX, $chunkZ, $random);
		}
	}
}
