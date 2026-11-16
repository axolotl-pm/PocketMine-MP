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
use pocketmine\utils\ObjectSet;
use pocketmine\utils\Utils;
use function mb_strtolower;

/**
 * A command enum whose entries can be changed at runtime.
 *
 * Alias keys are not sent to the clients because they cannot be hidden.
 *
 * @internal Use {@link CommandEnum::soft()} instead.
 *
 * @phpstan-template TValue
 * @phpstan-extends CommandEnum<TValue>
 */
final class CommandSoftEnum extends CommandEnum{

	/**
	 * @var mixed[]
	 * @phpstan-var array<string, TValue>
	 */
	private array $entries = [];

	/**
	 * @var true[]
	 * @phpstan-var array<string, true>
	 */
	private array $aliasKeys = [];

	/** @phpstan-var ObjectSet<\Closure(CommandSoftEnum<TValue>) : void> */
	private ObjectSet $changeHooks;

	/**
	 * @param mixed[]  $entries
	 * @param string[] $aliasKeys
	 *
	 * @phpstan-param array<array-key, TValue> $entries
	 * @phpstan-param list<string>             $aliasKeys
	 */
	public function __construct(
		string $name,
		private BaseType $valueType,
		array $entries = [],
		array $aliasKeys = []
	){
		parent::__construct($name);
		$this->changeHooks = new ObjectSet();
		$this->replace($entries, $aliasKeys);
	}

	public function getValueType() : BaseType{
		return $this->valueType;
	}

	public function getKeys() : array{
		$keys = [];
		foreach(Utils::stringifyKeys($this->entries) as $key => $_){
			if(!isset($this->aliasKeys[$key])){
				$keys[] = $key;
			}
		}
		return $keys;
	}

	public function lookup(string $key) : mixed{
		return $this->entries[$key] ?? null;
	}

	/**
	 * @phpstan-return ObjectSet<\Closure(CommandSoftEnum<TValue>) : void>
	 */
	public function getChangeHooks() : ObjectSet{
		return $this->changeHooks;
	}

	/**
	 * @param mixed[]  $entries
	 * @param string[] $aliasKeys
	 *
	 * @phpstan-param array<array-key, TValue> $entries
	 * @phpstan-param list<string>             $aliasKeys
	 */
	public function set(array $entries, array $aliasKeys = []) : void{
		$this->replace($entries, $aliasKeys);
		foreach($this->changeHooks as $hook){
			$hook($this);
		}
	}

	/**
	 * @param mixed[]  $entries
	 * @param string[] $aliasKeys
	 *
	 * @phpstan-param array<array-key, TValue> $entries
	 * @phpstan-param list<string>             $aliasKeys
	 */
	private function replace(array $entries, array $aliasKeys) : void{
		$normalizedEntries = [];
		foreach(Utils::stringifyKeys($entries) as $key => $value){
			$normalized = mb_strtolower($key);
			if(isset($normalizedEntries[$normalized])){
				throw new \InvalidArgumentException("Duplicate key \"$normalized\" in command enum " . $this->getName());
			}
			$normalizedEntries[$normalized] = $value;
		}
		$normalizedAliasKeys = [];
		foreach($aliasKeys as $aliasKey){
			$normalized = mb_strtolower($aliasKey);
			if(!isset($normalizedEntries[$normalized])){
				throw new \InvalidArgumentException("Alias key \"$normalized\" is not a key of command enum " . $this->getName());
			}
			$normalizedAliasKeys[$normalized] = true;
		}
		$this->entries = $normalizedEntries;
		$this->aliasKeys = $normalizedAliasKeys;
	}
}
