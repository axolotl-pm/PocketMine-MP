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

namespace pocketmine\command\defaults;

use pocketmine\command\overload\enum\CommandEnum;
use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\player\GameMode;
use pocketmine\world\World;

/**
 * Command enums shared by the built-in commands.
 *
 * @internal
 */
final class DefaultCommandEnums{

	private function __construct(){
		//NOOP
	}

	/**
	 * @phpstan-return CommandEnum<GameMode>
	 */
	public static function gameMode() : CommandEnum{
		/** @phpstan-var CommandEnum<GameMode>|null $enum */
		static $enum = null;

		//the numeric aliases are kept as hidden aliases so /gamemode 0 still works. Vanilla instead leaves
		//them out of the enum entirely and takes them through a separate integer overload
		return $enum ??= CommandEnum::nativeEnum(
			"GameMode",
			GameMode::class,
			static fn(GameMode $gameMode) => $gameMode->getAliases(),
			aliasKeys: ["0", "1", "2", "3"]
		);
	}

	/**
	 * @phpstan-return CommandEnum<int>
	 */
	public static function difficulty() : CommandEnum{
		/** @phpstan-var CommandEnum<int>|null $enum */
		static $enum = null;

		return $enum ??= CommandEnum::mapped("Difficulty", [
			"peaceful" => World::DIFFICULTY_PEACEFUL,
			"easy" => World::DIFFICULTY_EASY,
			"normal" => World::DIFFICULTY_NORMAL,
			"hard" => World::DIFFICULTY_HARD,
			"p" => World::DIFFICULTY_PEACEFUL,
			"e" => World::DIFFICULTY_EASY,
			"n" => World::DIFFICULTY_NORMAL,
			"h" => World::DIFFICULTY_HARD,
			"0" => World::DIFFICULTY_PEACEFUL,
			"1" => World::DIFFICULTY_EASY,
			"2" => World::DIFFICULTY_NORMAL,
			"3" => World::DIFFICULTY_HARD
		], "int", aliasKeys: ["0", "1", "2", "3"]);
	}

	/**
	 * @phpstan-return CommandEnum<Effect>
	 */
	public static function effect() : CommandEnum{
		/** @phpstan-var CommandEnum<Effect>|null $enum */
		static $enum = null;

		return $enum ??= CommandEnum::parser("Effect", static fn() => StringToEffectParser::getInstance(), Effect::class);
	}

	/**
	 * @phpstan-return CommandEnum<Enchantment>
	 */
	public static function enchantment() : CommandEnum{
		/** @phpstan-var CommandEnum<Enchantment>|null $enum */
		static $enum = null;

		return $enum ??= CommandEnum::parser("Enchant", static fn() => StringToEnchantmentParser::getInstance(), Enchantment::class);
	}
}
