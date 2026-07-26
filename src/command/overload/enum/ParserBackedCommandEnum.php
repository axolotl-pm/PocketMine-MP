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

namespace pocketmine\command\overload\enum;

use DaveRandom\CallbackValidator\Type\BaseType;
use pocketmine\utils\StringToTParser;
use function array_map;
use function array_values;
use function strval;

/**
 * @internal Use {@link CommandEnum::parser()} instead.
 *
 * @phpstan-template-covariant TValue
 * @phpstan-extends CommandEnum<TValue>
 */
final class ParserBackedCommandEnum extends CommandEnum{

	/** @phpstan-var StringToTParser<TValue>|null */
	private ?StringToTParser $parser = null;

	/**
	 * @phpstan-param \Closure() : StringToTParser<TValue> $parserProvider
	 */
	public function __construct(
		string $name,
		private \Closure $parserProvider,
		private BaseType $valueType
	){
		parent::__construct($name);
	}

	/** @phpstan-return StringToTParser<TValue> */
	private function getParser() : StringToTParser{
		return $this->parser ??= ($this->parserProvider)();
	}

	public function getValueType() : BaseType{
		return $this->valueType;
	}

	public function getKeys() : array{
		//getKnownAliases() is declared as string[]|int[], since PHP silently turns numeric-looking
		//array keys into ints
		return array_values(array_map(strval(...), $this->getParser()->getKnownAliases()));
	}

	public function lookup(string $key) : mixed{
		return $this->getParser()->parse($key);
	}
}
