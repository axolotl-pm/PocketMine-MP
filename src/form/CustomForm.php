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

use pocketmine\player\Player;
use pocketmine\utils\Utils;
use function array_is_list;
use function array_values;
use function count;
use function is_array;

/**
 * A questionnaire. Responses follow element order, including null placeholders for labels.
 */
final class CustomForm implements Form{
	/** @var list<FormElement> */
	private readonly array $elements;

	/**
	 * @param FormElement[]                                              $elements
	 * @param \Closure(Player, ?list<bool|int|float|string|null>) : void $onResponse
	 */
	public function __construct(
		private readonly string $title,
		array $elements,
		private readonly \Closure $onResponse
	){
		Utils::validateArrayValueType($elements, static function(FormElement $element) : void{});
		Utils::validateCallableSignature(static function(Player $player, ?array $values) : void{}, $onResponse);
		$this->elements = array_values($elements);
	}

	/** @return array{type: string, title: string, content: list<FormElement>} */
	public function jsonSerialize() : array{
		return ["type" => "custom_form", "title" => $this->title, "content" => $this->elements];
	}

	public function handleResponse(Player $player, $data) : void{
		$values = null;
		if($data !== null){
			if(!is_array($data) || !array_is_list($data) || count($data) !== count($this->elements)){
				throw new FormValidationException("Expected one response per form element");
			}
			$values = [];
			foreach($this->elements as $index => $element){
				$value = $data[$index];
				try{
					$element->validateResponse($value);
				}catch(FormValidationException $e){
					throw new FormValidationException("Invalid response for element $index: " . $e->getMessage(), 0, $e);
				}
				$values[] = $value;
			}
		}
		($this->onResponse)($player, $values);
	}
}
