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

namespace pocketmine\item;

class Trim{
	public const MATERIAL_QUARTZ = 'quartz';
	public const MATERIAL_IRON = 'iron';
	public const MATERIAL_COPPER = 'copper';
	public const MATERIAL_GOLD = 'gold';
	public const MATERIAL_LAPIS = 'lapis';
	public const MATERIAL_EMERALD = 'emerald';
	public const MATERIAL_DIAMOND = 'diamond';
	public const MATERIAL_REDSTONE = 'redstone';
	public const MATERIAL_AMETHYST = 'amethyst';
	public const MATERIAL_NETHERITE = 'netherite';

	public const PATTERN_SENTRY = 'sentry';
	public const PATTERN_DUNE = 'dune';
	public const PATTERN_COAST = 'coast';
	public const PATTERN_WILD = 'wild';
	public const PATTERN_WARD = 'ward';
	public const PATTERN_EYE = 'eye';
	public const PATTERN_VEX = 'vex';
	public const PATTERN_TIDE = 'tide';
	public const PATTERN_SNOUT = 'snout';
	public const PATTERN_RIB = 'rib';
	public const PATTERN_SPIRE = 'spire';
	public const PATTERN_WAYFINDER = 'wayfinder';
	public const PATTERN_SHAPER = 'shaper';
	public const PATTERN_RAISER = 'raiser';
	public const PATTERN_HOST = 'host';
	public const PATTERN_SILENCE = 'silence';

	private string $material;
	private string $pattern;

	public function __construct(
		string $material,
		string $pattern
	){
		$this->material = $material;
		$this->pattern = $pattern;
	}

	/**
	 * @return string
	 */
	public function getMaterial() : string{
		return $this->material;
	}

	/**
	 * @return string
	 */
	public function getPattern() : string{
		return $this->pattern;
	}
}