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
use pocketmine\utils\Utils;
use function count;
use function mb_strtolower;

/**
 * @internal Use the factory methods on {@link CommandEnum} instead.
 *
 * @phpstan-template-covariant TValue
 * @phpstan-extends CommandEnum<TValue>
 */
final class ArrayBackedCommandEnum extends CommandEnum{

	/**
	 * @var mixed[]
	 * @phpstan-var array<string, TValue>
	 */
	private array $entries = [];

	/**
	 * @var string[]
	 * @phpstan-var list<string>
	 */
	private array $keys = [];

	/**
	 * @var string[]
	 * @phpstan-var list<string>
	 */
	private array $aliasKeys = [];

	/**
	 * @param mixed[]  $entries
	 * @param string[] $aliasKeys
	 *
	 * @phpstan-param array<array-key, TValue> $entries
	 * @phpstan-param list<string>             $aliasKeys
	 */
	public function __construct(
		string $name,
		array $entries,
		private BaseType $valueType,
		array $aliasKeys = []
	){
		parent::__construct($name);

		foreach(Utils::stringifyKeys($entries) as $key => $value){
			$normalized = mb_strtolower($key);
			if(isset($this->entries[$normalized])){
				throw new \InvalidArgumentException("Duplicate key \"$normalized\" in command enum $name");
			}
			$this->entries[$normalized] = $value;
		}

		$isAlias = [];
		foreach($aliasKeys as $aliasKey){
			$normalized = mb_strtolower($aliasKey);
			if(!isset($this->entries[$normalized])){
				throw new \InvalidArgumentException("Alias key \"$normalized\" is not a key of command enum $name");
			}
			$isAlias[$normalized] = true;
			$this->aliasKeys[] = $normalized;
		}

		foreach(Utils::stringifyKeys($this->entries) as $key => $_){
			if(!isset($isAlias[$key])){
				$this->keys[] = $key;
			}
		}

		if(count($this->keys) === 0){
			throw new \InvalidArgumentException("Command enum $name must offer at least one non-alias key");
		}
	}

	public function getValueType() : BaseType{
		return $this->valueType;
	}

	public function getKeys() : array{
		return $this->keys;
	}

	public function getAliasKeys() : array{
		return $this->aliasKeys;
	}

	public function lookup(string $key) : mixed{
		return $this->entries[$key] ?? null;
	}
}
