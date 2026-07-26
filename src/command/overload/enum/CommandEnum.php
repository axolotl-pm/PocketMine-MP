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
use DaveRandom\CallbackValidator\Type\BuiltInType;
use DaveRandom\CallbackValidator\Type\NamedType;
use pocketmine\utils\StringToTParser;
use function mb_strtolower;

/**
 * A named set of string keys, each of which maps to a value of some type.
 *
 * The keys are advertised to the client, which uses them for completion and to reject invalid input
 * before anything is sent to the server.
 *
 * Instances are shared. An enum used by several commands is only transmitted once, so names must be
 * unique across the server; this is checked when a command is registered.
 *
 * @phpstan-template-covariant TValue
 */
abstract class CommandEnum{

	public function __construct(private string $name){}

	final public function getName() : string{
		return $this->name;
	}

	/**
	 * The type of the values produced by {@link CommandEnum::lookup()}. Used to validate the
	 * signature of the command handler the enum is passed to.
	 */
	abstract public function getValueType() : BaseType;

	/**
	 * The keys advertised to the client. Every key must be lowercase and must resolve to a non-null
	 * value via {@link CommandEnum::lookup()}.
	 *
	 * This is read while AvailableCommandsPacket is being built. Keys which change afterwards are not
	 * pushed to already-connected clients until commands are next synced for them.
	 *
	 * @return string[]
	 * @phpstan-return list<string>
	 */
	abstract public function getKeys() : array;

	/**
	 * Extra keys which are sent to the client but flagged so that it doesn't offer them for completion.
	 *
	 * Use this for spellings that are redundant with one already in {@link CommandEnum::getKeys()}, such
	 * as the numeric form of an otherwise named value. The client still accepts them, so typing one isn't
	 * rejected, but the completion list only shows one name per thing.
	 *
	 * @return string[]
	 * @phpstan-return list<string>
	 */
	public function getAliasKeys() : array{
		return [];
	}

	/**
	 * Resolves a lowercase key to its value, or null if the key isn't accepted.
	 *
	 * This may accept keys which {@link CommandEnum::getKeys()} doesn't advertise. That's used for
	 * input which is valid but not worth offering completion for, such as legacy numeric item IDs.
	 *
	 * @phpstan-return TValue|null
	 */
	abstract public function lookup(string $key) : mixed;

	/**
	 * Creates an enum whose values are the keys themselves.
	 *
	 * @param string[] $values
	 *
	 * @phpstan-param list<string> $values
	 * @phpstan-return self<string>
	 */
	public static function strings(string $name, array $values) : self{
		$entries = [];
		foreach($values as $value){
			$normalized = mb_strtolower($value);
			$entries[$normalized] = $normalized;
		}

		return new ArrayBackedCommandEnum($name, $entries, new NamedType(BuiltInType::STRING));
	}

	/**
	 * Creates an enum from an explicit key to value map. Keys are matched case-insensitively.
	 *
	 * Aliases are expressed by mapping several keys to the same value. Keys listed in $aliasKeys are
	 * still accepted, but the client won't offer them for completion. Use this for the numeric forms of
	 * an otherwise named value, and other redundant spellings.
	 *
	 * @param mixed[]  $entries
	 * @param string   $valueType Type of the mapped values, e.g. "int" or GameMode::class
	 * @param string[] $aliasKeys
	 *
	 * @phpstan-template TMapped
	 * @phpstan-param array<array-key, TMapped> $entries
	 * @phpstan-param list<string>              $aliasKeys
	 * @phpstan-return self<TMapped>
	 */
	public static function mapped(string $name, array $entries, string $valueType, array $aliasKeys = []) : self{
		return new ArrayBackedCommandEnum($name, $entries, new NamedType($valueType), $aliasKeys);
	}

	/**
	 * Creates an enum from the cases of a PHP native enum.
	 *
	 * By default, each case is keyed by its lowercased case name. Pass $aliasProvider to supply the
	 * keys for a case yourself, for example from an existing alias accessor.
	 *
	 * @param string[] $aliasKeys
	 *
	 * @phpstan-template TEnum of \UnitEnum
	 * @phpstan-param class-string<TEnum>                    $enumClass
	 * @phpstan-param (\Closure(TEnum) : array<string>)|null $keyProvider
	 * @phpstan-param list<string>                           $aliasKeys
	 * @phpstan-return self<TEnum>
	 */
	public static function nativeEnum(string $name, string $enumClass, ?\Closure $keyProvider = null, array $aliasKeys = []) : self{
		$entries = [];
		foreach($enumClass::cases() as $case){
			foreach($keyProvider !== null ? $keyProvider($case) : [$case->name] as $key){
				$entries[mb_strtolower($key)] = $case;
			}
		}

		return new ArrayBackedCommandEnum($name, $entries, new NamedType($enumClass), $aliasKeys);
	}

	/**
	 * Creates an enum backed by a {@link StringToTParser}. The advertised keys and the lookup both
	 * read the parser's alias map, so they can't disagree with each other.
	 *
	 * The parser is resolved lazily, so this may be called before the parser's registry is ready.
	 *
	 * @phpstan-template TParsed
	 * @phpstan-param \Closure() : StringToTParser<TParsed> $parserProvider
	 * @phpstan-return self<TParsed>
	 */
	public static function parser(string $name, \Closure $parserProvider, string $valueType) : self{
		return new ParserBackedCommandEnum($name, $parserProvider, new NamedType($valueType));
	}
}
