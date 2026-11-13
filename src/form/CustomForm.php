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
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use function array_keys;
use function count;
use function is_array;
use function spl_object_id;

final class CustomForm implements Form{

	/**
	 * @param FormElement[]|DisplayElement[] $elements
	 *
	 * @phpstan-param list<FormElement<mixed>|DisplayElement> $elements
	 * @phpstan-param \Closure(Player, CustomFormResponse) : void $onSubmit
	 * @phpstan-param (\Closure(Player, FormCloseReason) : void)|null $onClose
	 */
	public function __construct(
		public readonly string $title,
		public readonly array $elements,
		private readonly \Closure $onSubmit,
		private readonly ?\Closure $onClose = null
	){
		Utils::validateArrayValueType($elements, function(FormElement|DisplayElement $_) : void{});
		$seenInputs = [];
		foreach($elements as $element){
			if(!$element instanceof FormElement){
				continue;
			}
			$id = spl_object_id($element);
			if(isset($seenInputs[$id])){
				throw new \InvalidArgumentException("Cannot add same FormElement to a form more than once");
			}
			$seenInputs[$id] = true;
		}
	}

	public function handleResponse(Player $player, $data) : void{
		if(!is_array($data) || array_keys($data) !== array_keys($this->elements)){
			throw new FormValidationException("Expected a list of " . count($this->elements) . " values");
		}
		$values = [];
		foreach($this->elements as $index => $element){
			$values[] = $element instanceof FormElement ? $element->parse($data[$index]) : null;
		}
		($this->onSubmit)($player, new CustomFormResponse($this->elements, $values));
	}

	public function handleClose(Player $player, FormCloseReason $reason) : void{
		if($this->onClose !== null){
			($this->onClose)($player, $reason);
		}
	}

	public function jsonSerialize() : mixed{
		return [
			FormKeys::TYPE => "custom_form",
			FormKeys::TITLE => $this->title,
			FormKeys::CONTENT => $this->elements
		];
	}
}
