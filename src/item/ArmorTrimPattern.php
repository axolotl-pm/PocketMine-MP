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

/**
 * Patterns which can be applied to a piece of armour using a smithing table and the matching smithing template.
 */
enum ArmorTrimPattern{

	case BOLT;
	case COAST;
	case DUNE;
	case EYE;
	case FLOW;
	case HOST;
	case RAISER;
	case RIB;
	case SENTRY;
	case SHAPER;
	case SILENCE;
	case SNOUT;
	case SPIRE;
	case TIDE;
	case VEX;
	case WARD;
	case WAYFINDER;
	case WILD;

	/**
	 * Returns the pattern matching the given smithing template item, or null if the item is not an armour trim
	 * smithing template.
	 */
	public static function fromItem(Item $item) : ?self{
		foreach(self::cases() as $case){
			if($case->getTemplate()->getTypeId() === $item->getTypeId()){
				return $case;
			}
		}
		return null;
	}

	/**
	 * Returns the smithing template item which must be placed in the smithing table's template slot to apply this
	 * pattern.
	 */
	public function getTemplate() : Item{
		return match($this){
			self::BOLT => VanillaItems::BOLT_ARMOR_TRIM_SMITHING_TEMPLATE(),
			self::COAST => VanillaItems::COAST_ARMOR_TRIM_SMITHING_TEMPLATE(),
			self::DUNE => VanillaItems::DUNE_ARMOR_TRIM_SMITHING_TEMPLATE(),
			self::EYE => VanillaItems::EYE_ARMOR_TRIM_SMITHING_TEMPLATE(),
			self::FLOW => VanillaItems::FLOW_ARMOR_TRIM_SMITHING_TEMPLATE(),
			self::HOST => VanillaItems::HOST_ARMOR_TRIM_SMITHING_TEMPLATE(),
			self::RAISER => VanillaItems::RAISER_ARMOR_TRIM_SMITHING_TEMPLATE(),
			self::RIB => VanillaItems::RIB_ARMOR_TRIM_SMITHING_TEMPLATE(),
			self::SENTRY => VanillaItems::SENTRY_ARMOR_TRIM_SMITHING_TEMPLATE(),
			self::SHAPER => VanillaItems::SHAPER_ARMOR_TRIM_SMITHING_TEMPLATE(),
			self::SILENCE => VanillaItems::SILENCE_ARMOR_TRIM_SMITHING_TEMPLATE(),
			self::SNOUT => VanillaItems::SNOUT_ARMOR_TRIM_SMITHING_TEMPLATE(),
			self::SPIRE => VanillaItems::SPIRE_ARMOR_TRIM_SMITHING_TEMPLATE(),
			self::TIDE => VanillaItems::TIDE_ARMOR_TRIM_SMITHING_TEMPLATE(),
			self::VEX => VanillaItems::VEX_ARMOR_TRIM_SMITHING_TEMPLATE(),
			self::WARD => VanillaItems::WARD_ARMOR_TRIM_SMITHING_TEMPLATE(),
			self::WAYFINDER => VanillaItems::WAYFINDER_ARMOR_TRIM_SMITHING_TEMPLATE(),
			self::WILD => VanillaItems::WILD_ARMOR_TRIM_SMITHING_TEMPLATE()
		};
	}
}
