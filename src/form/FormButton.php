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

use function str_starts_with;

/**
 * A simple-form button with an optional resource-pack path or HTTP(S) image URL.
 */
final class FormButton implements \JsonSerializable{

	public function __construct(
		private readonly string $text,
		private readonly ?string $image = null
	){}

	/** @return array{text: string, image?: array{type: string, data: string}} */
	public function jsonSerialize() : array{
		$data = ["text" => $this->text];
		if($this->image !== null){
			$data["image"] = [
				"type" => str_starts_with($this->image, "https://") || str_starts_with($this->image, "http://") ? "url" : "path",
				"data" => $this->image
			];
		}
		return $data;
	}
}
