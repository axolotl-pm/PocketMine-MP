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
use function is_bool;

/**
 * A two-button dialog. True selects button 1, false selects button 2, and null means canceled.
 */
final class ModalForm implements Form{

	/** @param \Closure(Player, ?bool) : void $onResponse */
	public function __construct(
		private readonly string $title,
		private readonly string $content,
		private readonly string $button1,
		private readonly string $button2,
		private readonly \Closure $onResponse
	){
		Utils::validateCallableSignature(static function(Player $player, ?bool $selection) : void{}, $onResponse);
	}

	/** @return array{type: string, title: string, content: string, button1: string, button2: string} */
	public function jsonSerialize() : array{
		return ["type" => "modal", "title" => $this->title, "content" => $this->content, "button1" => $this->button1, "button2" => $this->button2];
	}

	public function handleResponse(Player $player, $data) : void{
		if($data !== null && !is_bool($data)){
			throw new FormValidationException("Expected a boolean or null");
		}
		($this->onResponse)($player, $data);
	}
}
