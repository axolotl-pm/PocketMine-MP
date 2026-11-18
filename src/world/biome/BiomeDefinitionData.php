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

namespace pocketmine\world\biome;

use pocketmine\color\Color;

final class BiomeDefinitionData{

	/**
	 * @param string[] $tags
	 * @phpstan-param list<string> $tags
	 */
	public function __construct(
		private readonly int $definitionId,
		private readonly float $temperature,
		private readonly float $downfall,
		private readonly float $foliageSnow,
		private readonly float $depth,
		private readonly float $scale,
		private readonly Color $mapWaterColour,
		private readonly bool $rain,
		private readonly array $tags
	){}

	public function getDefinitionId() : int{ return $this->definitionId; }

	public function getTemperature() : float{ return $this->temperature; }

	public function getDownfall() : float{ return $this->downfall; }

	public function getFoliageSnow() : float{ return $this->foliageSnow; }

	public function getDepth() : float{ return $this->depth; }

	public function getScale() : float{ return $this->scale; }

	public function getMapWaterColour() : Color{ return $this->mapWaterColour; }

	public function hasRain() : bool{ return $this->rain; }

	/**
	 * @return string[]
	 * @phpstan-return list<string>
	 */
	public function getTags() : array{ return $this->tags; }
}
