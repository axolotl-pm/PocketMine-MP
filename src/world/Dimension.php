<?php

declare(strict_types=1);

namespace pocketmine\world;

use pocketmine\world\biome\Biome;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\SubChunk;
use function str_contains;
use function str_starts_with;

final class Dimension{

	private function __construct(
		private readonly string $name,
		private readonly int $minY,
		private readonly int $maxY,
		private readonly Biome $defaultBiome,
		private readonly int $cloudHeight,
		private readonly bool $renderClouds,
		private readonly bool $vanilla
	){}

	public function getName() : string{
		return $this->name;
	}

	public function getMinY() : int{
		return $this->minY;
	}

	public function getMaxY() : int{
		return $this->maxY;
	}

	public function getDefaultBiome() : Biome{
		return $this->defaultBiome;
	}

	public function getCloudHeight() : int{
		return $this->cloudHeight;
	}

	public function shouldRenderClouds() : bool{
		return $this->renderClouds;
	}

	public function isVanilla() : bool{
		return $this->vanilla;
	}

	public static function create(
		string $name,
		int $minY,
		int $maxY,
		Biome $defaultBiome,
		int $cloudHeight = 128,
		bool $renderClouds = false
	) : self{
		if(!str_contains($name, ":")){
			throw new \InvalidArgumentException("Dimension name must contain a namespace prefix");
		}
		if(str_starts_with($name, "minecraft:")){
			throw new \InvalidArgumentException("Dimension name cannot use the \"minecraft\" namespace");
		}
		self::validateHeightRange($minY, $maxY);
		return new self($name, $minY, $maxY, $defaultBiome, $cloudHeight, $renderClouds, false);
	}

	/**
	 * @internal Used only for creating vanilla dimensions.
	 * Use {@link create()} for custom dimensions.
	 */
	public static function createVanilla(
		string $name,
		int $minY,
		int $maxY,
		Biome $defaultBiome,
		int $cloudHeight = 128,
		bool $renderClouds = false
	) : self{
		return new self($name, $minY, $maxY, $defaultBiome, $cloudHeight, $renderClouds, true);
	}

	private static function validateHeightRange(int $minY, int $maxY) : void{
		$worldMinY = Chunk::MIN_SUBCHUNK_INDEX << SubChunk::COORD_BIT_SIZE;
		$worldMaxY = (Chunk::MAX_SUBCHUNK_INDEX + 1) << SubChunk::COORD_BIT_SIZE;
		if($minY < $worldMinY || $maxY > $worldMaxY || $minY >= $maxY){
			//TODO: we should allow whatever height range, but I'm not sure allowing that does not break anything else
			throw new \InvalidArgumentException("Dimension height range must be within $worldMinY..$worldMaxY, got $minY..$maxY");
		}
		if($minY % SubChunk::EDGE_LENGTH !== 0 || $maxY % SubChunk::EDGE_LENGTH !== 0){
			throw new \InvalidArgumentException("Dimension height range must be aligned to " . SubChunk::EDGE_LENGTH . " blocks, got $minY..$maxY");
		}
	}
}
