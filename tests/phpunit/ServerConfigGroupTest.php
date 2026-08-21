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

namespace pocketmine;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pocketmine\utils\Config;

class ServerConfigGroupTest extends TestCase{

	/**
	 * @return iterable<string, array{bool|null, bool}>
	 */
	public static function blockNetworkIdHashesProvider() : iterable{
		yield "setting absent" => [null, false];
		yield "setting disabled" => [false, false];
		yield "setting enabled" => [true, true];
	}

	#[DataProvider("blockNetworkIdHashesProvider")]
	public function testBlockNetworkIdHashesSetting(?bool $configuredValue, bool $expected) : void{
		$pocketmineYml = self::createStub(Config::class);
		$pocketmineYml->method("getNested")->willReturnCallback(
			static fn(string $property) => $property === YmlServerProperties::NETWORK_BLOCK_NETWORK_IDS_ARE_HASHES ? $configuredValue : null
		);

		$serverProperties = self::createStub(Config::class);
		$configGroup = new ServerConfigGroup($pocketmineYml, $serverProperties);

		self::assertSame(
			$expected,
			$configGroup->getPropertyBool(YmlServerProperties::NETWORK_BLOCK_NETWORK_IDS_ARE_HASHES, false)
		);
	}
}
