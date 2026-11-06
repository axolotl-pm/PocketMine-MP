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
use function array_values;
use function is_int;

/**
 * A button menu. The response is a zero-based button index, or null when canceled.
 */
final class SimpleForm implements Form{
	/** @var list<FormButton> */
	private readonly array $buttons;

	/**
	 * @param FormButton[]                  $buttons
	 * @param \Closure(Player, ?int) : void $onResponse
	 */
	public function __construct(
		private readonly string $title,
		private readonly string $content,
		array $buttons,
		private readonly \Closure $onResponse
	){
		Utils::validateArrayValueType($buttons, static function(FormButton $button) : void{});
		Utils::validateCallableSignature(static function(Player $player, ?int $selection) : void{}, $onResponse);
		$this->buttons = array_values($buttons);
	}

	/** @return array{type: string, title: string, content: string, buttons: list<FormButton>} */
	public function jsonSerialize() : array{
		return ["type" => "form", "title" => $this->title, "content" => $this->content, "buttons" => $this->buttons];
	}

	public function handleResponse(Player $player, $data) : void{
		if($data !== null && (!is_int($data) || !isset($this->buttons[$data]))){
			throw new FormValidationException("Expected a valid button index or null");
		}
		($this->onResponse)($player, $data);
	}
}
