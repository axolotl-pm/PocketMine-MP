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

namespace pocketmine\data\bedrock;

use pocketmine\data\bedrock\ArmorTrimPatternTypeIds as Ids;
use pocketmine\item\ArmorTrimPattern;
use pocketmine\utils\SingletonTrait;
use function array_key_exists;
use function spl_object_id;

final class ArmorTrimPatternTypeIdMap{
	use SingletonTrait;

	/**
	 * @var ArmorTrimPattern[]
	 * @phpstan-var array<string, ArmorTrimPattern>
	 */
	private array $idToEnum = [];
	/**
	 * @var string[]
	 * @phpstan-var array<int, string>
	 */
	private array $enumToId = [];

	public function __construct(){
		foreach(ArmorTrimPattern::cases() as $case){
			$this->register(match($case){
				ArmorTrimPattern::BOLT => Ids::BOLT,
				ArmorTrimPattern::COAST => Ids::COAST,
				ArmorTrimPattern::DUNE => Ids::DUNE,
				ArmorTrimPattern::EYE => Ids::EYE,
				ArmorTrimPattern::FLOW => Ids::FLOW,
				ArmorTrimPattern::HOST => Ids::HOST,
				ArmorTrimPattern::RAISER => Ids::RAISER,
				ArmorTrimPattern::RIB => Ids::RIB,
				ArmorTrimPattern::SENTRY => Ids::SENTRY,
				ArmorTrimPattern::SHAPER => Ids::SHAPER,
				ArmorTrimPattern::SILENCE => Ids::SILENCE,
				ArmorTrimPattern::SNOUT => Ids::SNOUT,
				ArmorTrimPattern::SPIRE => Ids::SPIRE,
				ArmorTrimPattern::TIDE => Ids::TIDE,
				ArmorTrimPattern::VEX => Ids::VEX,
				ArmorTrimPattern::WARD => Ids::WARD,
				ArmorTrimPattern::WAYFINDER => Ids::WAYFINDER,
				ArmorTrimPattern::WILD => Ids::WILD
			}, $case);
		}
	}

	public function register(string $stringId, ArmorTrimPattern $pattern) : void{
		$this->idToEnum[$stringId] = $pattern;
		$this->enumToId[spl_object_id($pattern)] = $stringId;
	}

	public function fromId(string $id) : ?ArmorTrimPattern{
		return $this->idToEnum[$id] ?? null;
	}

	public function toId(ArmorTrimPattern $pattern) : string{
		$k = spl_object_id($pattern);
		if(!array_key_exists($k, $this->enumToId)){
			throw new \InvalidArgumentException("Missing mapping for armor trim pattern " . $pattern->name);
		}
		return $this->enumToId[$k];
	}
}
