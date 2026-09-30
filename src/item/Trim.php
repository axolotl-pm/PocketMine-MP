<?php

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