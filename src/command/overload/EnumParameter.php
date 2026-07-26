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

namespace pocketmine\command\overload;

use pocketmine\command\overload\enum\CommandEnum;
use pocketmine\command\utils\CommandStringHelper;
use pocketmine\lang\Translatable;
use function mb_strtolower;

/**
 * Accepts one of a fixed set of strings and converts it to some type of value.
 *
 * Unlike {@link MappedParameter}, the accepted strings are sent to the client, which uses them for
 * completion and to reject invalid input before the command reaches the server.
 *
 * @phpstan-template TValue
 * @phpstan-extends Parameter<TValue>
 */
final class EnumParameter extends Parameter{

	/**
	 * @phpstan-param CommandEnum<TValue> $enum
	 */
	public function __construct(
		string $codeName,
		Translatable|string $printableName,
		private CommandEnum $enum
	){
		parent::__construct($codeName, $printableName, $enum->getValueType());
	}

	/**
	 * @phpstan-return CommandEnum<TValue>
	 */
	public function getEnum() : CommandEnum{
		return $this->enum;
	}

	public function parse(string $buffer, int &$offset) : mixed{
		$raw = CommandStringHelper::parseQuoteAwareSingle($buffer, $offset) ?? throw new ParameterParseException("Unable to parse an argument from the buffer");
		return $this->enum->lookup(mb_strtolower($raw)) ?? throw new ParameterParseException("Invalid value for " . $this->getCodeName() . ": $raw");
	}
}
