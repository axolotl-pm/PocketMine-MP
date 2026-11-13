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
use pocketmine\form\element\MenuButton;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use function array_filter;
use function count;
use function is_int;

final class MenuForm implements Form{

	private readonly int $buttonCount;

	/**
	 * @param MenuButton[]|DisplayElement[] $elements
	 *
	 * @phpstan-param list<MenuButton|DisplayElement> $elements
	 * @phpstan-param \Closure(Player, int $buttonIndex) : void $onSubmit
	 * @phpstan-param (\Closure(Player, FormCloseReason) : void)|null $onClose
	 */
	public function __construct(
		public readonly string $title,
		public readonly string $content,
		public readonly array $elements,
		private readonly \Closure $onSubmit,
		private readonly ?\Closure $onClose = null
	){
		Utils::validateArrayValueType($elements, function(MenuButton|DisplayElement $_) : void{});
		$this->buttonCount = count(array_filter($elements, fn(MenuButton|DisplayElement $element) => $element instanceof MenuButton));
	}

	public function handleResponse(Player $player, $data) : void{
		if(!is_int($data) || $data < 0 || $data >= $this->buttonCount){
			throw new FormValidationException("Invalid button index");
		}
		($this->onSubmit)($player, $data);
	}

	public function handleClose(Player $player, FormCloseReason $reason) : void{
		if($this->onClose !== null){
			($this->onClose)($player, $reason);
		}
	}

	public function jsonSerialize() : mixed{
		return [
			FormKeys::TYPE => "form",
			FormKeys::TITLE => $this->title,
			FormKeys::CONTENT => $this->content,
			FormKeys::ELEMENTS => $this->elements
		];
	}
}
