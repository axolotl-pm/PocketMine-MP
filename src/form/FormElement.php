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

namespace pocketmine\form;

use pocketmine\utils\Utils;
use function array_values;
use function is_finite;
use function is_float;
use function is_int;

/**
 * Immutable custom-form controls with validation matching their serialized settings.
 */
final class FormElement implements \JsonSerializable{

	/**
	 * @param array<string, bool|int|float|string|list<string>> $data
	 * @param \Closure(mixed) : bool                            $validator
	 */
	private function __construct(
		private readonly array $data,
		private readonly \Closure $validator
	){}

	public static function label(string $text) : self{
		return new self(["type" => "label", "text" => $text], static fn(mixed $value) : bool => $value === null);
	}

	public static function input(string $text, string $placeholder = "", string $default = "") : self{
		return new self(["type" => "input", "text" => $text, "placeholder" => $placeholder, "default" => $default], is_string(...));
	}

	public static function toggle(string $text, bool $default = false) : self{
		return new self(["type" => "toggle", "text" => $text, "default" => $default], is_bool(...));
	}

	public static function slider(string $text, float $min, float $max, float $step = 1.0, ?float $default = null) : self{
		$default ??= $min;
		if(!is_finite($min) || !is_finite($max) || !is_finite($step) || !is_finite($default) || $min > $max || $step <= 0 || $default < $min || $default > $max){
			throw new \InvalidArgumentException("Slider values must be finite, min <= default <= max, and step > 0");
		}
		return new self(
			["type" => "slider", "text" => $text, "min" => $min, "max" => $max, "step" => $step, "default" => $default],
			static fn(mixed $value) : bool => (is_int($value) || is_float($value)) && is_finite($value) && $value >= $min && $value <= $max
		);
	}

	/** @param string[] $options */
	public static function dropdown(string $text, array $options, int $default = 0) : self{
		return self::choice("dropdown", "options", $text, $options, $default);
	}

	/** @param string[] $steps */
	public static function stepSlider(string $text, array $steps, int $default = 0) : self{
		return self::choice("step_slider", "steps", $text, $steps, $default);
	}

	/** @param string[] $options */
	private static function choice(string $type, string $key, string $text, array $options, int $default) : self{
		Utils::validateArrayValueType($options, static function(string $option) : void{});
		$options = array_values($options);
		if(!isset($options[$default])){
			throw new \InvalidArgumentException("Choices must not be empty and the default index must exist");
		}
		return new self(
			["type" => $type, "text" => $text, $key => $options, "default" => $default],
			static fn(mixed $value) : bool => is_int($value) && isset($options[$value])
		);
	}

	/** @return array<string, bool|int|float|string|list<string>> */
	public function jsonSerialize() : array{
		return $this->data;
	}

	/**
	 * @throws FormValidationException
	 * @phpstan-assert bool|int|float|string|null $value
	 */
	public function validateResponse(mixed $value) : void{
		if(!($this->validator)($value)){
			throw new FormValidationException("Unexpected form element value");
		}
	}
}
