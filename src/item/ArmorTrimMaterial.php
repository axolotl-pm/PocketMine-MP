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

use pocketmine\utils\TextFormat;

/**
 * Materials which can be used to apply a trim to a piece of armour using a smithing table.
 */
enum ArmorTrimMaterial{

	case AMETHYST;
	case COPPER;
	case DIAMOND;
	case EMERALD;
	case GOLD;
	case IRON;
	case LAPIS;
	case NETHERITE;
	case QUARTZ;
	case REDSTONE;
	case RESIN;

	/**
	 * Returns the material matching the given item, or null if the item cannot be used as an armour trim material.
	 */
	public static function fromItem(Item $item) : ?self{
		foreach(self::cases() as $case){
			if($case->getItem()->getTypeId() === $item->getTypeId()){
				return $case;
			}
		}
		return null;
	}

	/**
	 * Returns the item which must be placed in the smithing table's material slot to apply this trim material.
	 */
	public function getItem() : Item{
		return match($this){
			self::AMETHYST => VanillaItems::AMETHYST_SHARD(),
			self::COPPER => VanillaItems::COPPER_INGOT(),
			self::DIAMOND => VanillaItems::DIAMOND(),
			self::EMERALD => VanillaItems::EMERALD(),
			self::GOLD => VanillaItems::GOLD_INGOT(),
			self::IRON => VanillaItems::IRON_INGOT(),
			self::LAPIS => VanillaItems::LAPIS_LAZULI(),
			self::NETHERITE => VanillaItems::NETHERITE_INGOT(),
			self::QUARTZ => VanillaItems::NETHER_QUARTZ(),
			self::REDSTONE => VanillaItems::REDSTONE_DUST(),
			self::RESIN => VanillaItems::RESIN_BRICK()
		};
	}

	/**
	 * Returns the text format code used by the client to colour the trim description in the item tooltip.
	 */
	public function getColor() : string{
		return match($this){
			self::AMETHYST => TextFormat::MATERIAL_AMETHYST,
			self::COPPER => TextFormat::MATERIAL_COPPER,
			self::DIAMOND => TextFormat::MATERIAL_DIAMOND,
			self::EMERALD => TextFormat::MATERIAL_EMERALD,
			self::GOLD => TextFormat::MATERIAL_GOLD,
			self::IRON => TextFormat::MATERIAL_IRON,
			self::LAPIS => TextFormat::MATERIAL_LAPIS,
			self::NETHERITE => TextFormat::MATERIAL_NETHERITE,
			self::QUARTZ => TextFormat::MATERIAL_QUARTZ,
			self::REDSTONE => TextFormat::MATERIAL_REDSTONE,
			self::RESIN => TextFormat::MATERIAL_RESIN
		};
	}
}
