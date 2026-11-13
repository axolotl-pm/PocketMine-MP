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

namespace pocketmine\form\element;

use pocketmine\form\FormKeys;
use pocketmine\form\FormValidationException;
use function count;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * An input element of a custom form. TValue is the type of the value read from the player's response.
 *
 * @phpstan-template-covariant TValue
 */
final class FormElement implements \JsonSerializable{

	/**
	 * @phpstan-param array<string, mixed> $data
	 * @phpstan-param \Closure(mixed) : TValue $parser
	 */
	private function __construct(
		private readonly array $data,
		private readonly \Closure $parser
	){}

	/**
	 * Reads the response as the index of the selected option.
	 *
	 * @param string[] $options
	 * @phpstan-param list<string> $options
	 *
	 * @phpstan-return self<int>
	 */
	public static function dropdown(string $text, array $options, int $default = 0, string $tooltip = "") : self{
		return self::choice([FormKeys::TYPE => "dropdown", FormKeys::TEXT => $text, FormKeys::OPTIONS => $options, FormKeys::TOOLTIP => $tooltip], $text, $options, $default);
	}

	/**
	 * @phpstan-return self<string>
	 */
	public static function input(string $text, string $placeholder = "", string $default = "", string $tooltip = "") : self{
		return new self(
			[FormKeys::TYPE => "input", FormKeys::TEXT => $text, FormKeys::PLACEHOLDER => $placeholder, FormKeys::DEFAULT => $default, FormKeys::TOOLTIP => $tooltip],
			static fn(mixed $value) : string => is_string($value) ? $value : throw new FormValidationException("Expected string for input \"$text\"")
		);
	}

	/**
	 * @param float|null $default defaults to $min
	 *
	 * @phpstan-return self<float>
	 */
	public static function slider(string $text, float $min, float $max, float $step = 1.0, ?float $default = null, string $tooltip = "") : self{
		if($min >= $max){
			throw new \InvalidArgumentException("Min must be less than max");
		}
		if($step <= 0){
			throw new \InvalidArgumentException("Step must be positive");
		}
		$default ??= $min;
		if($default < $min || $default > $max){
			throw new \InvalidArgumentException("Default must be between min and max");
		}
		return new self(
			[FormKeys::TYPE => "slider", FormKeys::TEXT => $text, FormKeys::MIN => $min, FormKeys::MAX => $max, FormKeys::STEP => $step, FormKeys::DEFAULT => $default, FormKeys::TOOLTIP => $tooltip],
			static function(mixed $value) use ($text, $min, $max) : float{
				if(!is_int($value) && !is_float($value)){
					throw new FormValidationException("Expected number for slider \"$text\"");
				}
				if($value < $min || $value > $max){
					throw new FormValidationException("Value for slider \"$text\" is out of range");
				}
				return (float) $value;
			}
		);
	}

	/**
	 * Reads the response as the index of the selected step.
	 *
	 * @param string[] $steps
	 * @phpstan-param list<string> $steps
	 *
	 * @phpstan-return self<int>
	 */
	public static function stepSlider(string $text, array $steps, int $default = 0) : self{
		return self::choice([FormKeys::TYPE => "step_slider", FormKeys::TEXT => $text, FormKeys::STEPS => $steps], $text, $steps, $default);
	}

	/**
	 * @phpstan-return self<bool>
	 */
	public static function toggle(string $text, bool $default = false, string $tooltip = "") : self{
		return new self(
			[FormKeys::TYPE => "toggle", FormKeys::TEXT => $text, FormKeys::DEFAULT => $default, FormKeys::TOOLTIP => $tooltip],
			static fn(mixed $value) : bool => is_bool($value) ? $value : throw new FormValidationException("Expected bool for toggle \"$text\"")
		);
	}

	/**
	 * @phpstan-param array<string, mixed> $data
	 * @phpstan-param list<string>         $choices
	 * @phpstan-return self<int>
	 */
	private static function choice(array $data, string $text, array $choices, int $default) : self{
		if(count($choices) === 0){
			throw new \InvalidArgumentException("At least one choice is required");
		}
		if(!isset($choices[$default])){
			throw new \InvalidArgumentException("Default index $default is out of range");
		}
		$data[FormKeys::DEFAULT] = $default;
		return new self(
			$data,
			static fn(mixed $value) : int => is_int($value) && isset($choices[$value]) ? $value : throw new FormValidationException("Invalid choice index for \"$text\"")
		);
	}

	/**
	 * @return mixed[]
	 * @phpstan-return array<string, mixed>
	 */
	public function jsonSerialize() : array{
		return $this->data;
	}

	/**
	 * @internal
	 * @phpstan-return TValue
	 * @throws FormValidationException
	 */
	public function parse(mixed $value) : mixed{
		return ($this->parser)($value);
	}
}
