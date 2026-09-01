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

namespace pocketmine\network;

use function array_values;
use function explode;
use function strtolower;
use function trim;

enum Transport : string{

	private const MAX_LISTED = 8;

	case RAKNET = "raknet";
	case NETHERNET = "nethernet";

	/**
	 * Parses a comma-separated list of transport names.
	 *
	 * @phpstan-return array{list<self>, list<string>} Returns [valid transports, unknown transport names]
	 */
	public static function parseList(string $value) : array{
		$transports = [];
		$unknown = [];

		foreach(explode(",", $value, self::MAX_LISTED) as $name){
			$name = strtolower(trim($name));
			if($name === ""){
				continue;
			}

			//"both" is an alias to enable all supported transports
			if($name === "both"){
				foreach(self::cases() as $case){
					$transports[$case->value] = $case;
				}
				continue;
			}

			$transport = self::tryFrom($name);
			if($transport === null){
				$unknown[] = $name;
			}else{
				$transports[$transport->value] = $transport;
			}
		}

		return [array_values($transports), $unknown];
	}
}
