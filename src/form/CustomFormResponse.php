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

use pocketmine\form\element\DisplayElement;
use pocketmine\form\element\FormElement;
use function array_search;

final class CustomFormResponse{

	/**
	 * @internal
	 *
	 * @param FormElement[]|DisplayElement[] $elements
	 * @param mixed[]                        $values
	 *
	 * @phpstan-param list<FormElement<mixed>|DisplayElement> $elements
	 * @phpstan-param list<mixed>              $values
	 */
	public function __construct(
		private readonly array $elements,
		private readonly array $values
	){}

	/**
	 * @phpstan-template T
	 * @phpstan-param FormElement<T> $element
	 * @phpstan-return T
	 */
	public function getValue(FormElement $element) : mixed{
		$index = array_search($element, $this->elements, true);
		if($index === false){
			throw new \InvalidArgumentException("Element not found in form");
		}
		/** @phpstan-var T $value */
		$value = $this->values[$index];
		return $value;
	}
}
