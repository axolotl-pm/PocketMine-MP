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

/**
 * A non-interactive element usable in both custom forms and menu forms.
 */
final class DisplayElement implements \JsonSerializable{

	/**
	 * @phpstan-param array<string, mixed> $data
	 */
	private function __construct(
		private readonly array $data
	){}

	public static function divider() : self{
		return new self([FormKeys::TYPE => "divider", FormKeys::TEXT => ""]);
	}

	public static function header(string $text) : self{
		return new self([FormKeys::TYPE => "header", FormKeys::TEXT => $text]);
	}

	public static function label(string $text) : self{
		return new self([FormKeys::TYPE => "label", FormKeys::TEXT => $text]);
	}

	/**
	 * @return mixed[]
	 * @phpstan-return array<string, mixed>
	 */
	public function jsonSerialize() : array{
		return $this->data;
	}
}
