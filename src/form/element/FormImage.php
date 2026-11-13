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

final class FormImage implements \JsonSerializable{

	private function __construct(
		private readonly string $type,
		private readonly string $data
	){}

	public static function path(string $path) : self{
		return new self("path", $path);
	}

	public static function url(string $url) : self{
		return new self("url", $url);
	}

	/**
	 * @return string[]
	 * @phpstan-return array{type: string, data: string}
	 */
	public function jsonSerialize() : array{
		return [FormKeys::TYPE => $this->type, FormKeys::IMAGE_DATA => $this->data];
	}
}
