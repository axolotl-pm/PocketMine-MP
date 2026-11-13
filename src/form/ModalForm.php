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
use function is_bool;

final class ModalForm implements Form{

	/**
	 * @phpstan-param \Closure(Player, bool $confirmed) : void $onSubmit $confirmed is true for button1, false for button2
	 * @phpstan-param (\Closure(Player, FormCloseReason) : void)|null $onClose
	 */
	public function __construct(
		public readonly string $title,
		public readonly string $content,
		public readonly string $button1,
		public readonly string $button2,
		private readonly \Closure $onSubmit,
		private readonly ?\Closure $onClose = null
	){
	}

	public function handleResponse(Player $player, $data) : void{
		if(!is_bool($data)){
			throw new FormValidationException("Expected bool");
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
			FormKeys::TYPE => "modal",
			FormKeys::TITLE => $this->title,
			FormKeys::CONTENT => $this->content,
			FormKeys::BUTTON1 => $this->button1,
			FormKeys::BUTTON2 => $this->button2
		];
	}
}
