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

namespace pocketmine\network\mcpe\nethernet;

use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use pocketmine\utils\Utils;
use function count;
use function get_debug_type;
use function is_array;
use function is_int;
use function is_string;

final class NetherNetIceConfiguration extends ThreadSafe{

	private const DEFAULT_ICE_SERVER_PORT = 3478;

	/** @phpstan-var ThreadSafeArray<int, NetherNetIceServer> */
	private ThreadSafeArray $servers;

	/**
	 * Both port range bounds are null when any port may be used.
	 *
	 * @param NetherNetIceServer[] $servers
	 */
	public function __construct(
		array $servers,
		private bool $udpMux,
		private ?int $portRangeBegin,
		private ?int $portRangeEnd
	){
		$this->servers = ThreadSafeArray::fromArray($servers);
	}

	public static function parse(mixed $iceServers, mixed $portRange, bool $udpMux) : self{
		[$portRangeBegin, $portRangeEnd] = self::parsePortRange($portRange);

		if($iceServers === null){
			$iceServers = [];
		}elseif(!is_array($iceServers)){
			throw new \InvalidArgumentException("ice-servers must be a list of ICE servers, got " . get_debug_type($iceServers));
		}

		$servers = [];
		foreach(Utils::promoteKeys($iceServers) as $index => $entry){
			$server = self::parseServer($entry, "ice-servers entry $index");
			if($udpMux && $server->isTurn()){
				throw new \InvalidArgumentException("ice-servers entry $index is a TURN server, which cannot be used while ice-udp-mux is enabled");
			}
			$servers[] = $server;
		}

		return new self($servers, $udpMux, $portRangeBegin, $portRangeEnd);
	}

	/**
	 * @throws \InvalidArgumentException
	 *
	 * @phpstan-return array{int|null, int|null}
	 */
	private static function parsePortRange(mixed $portRange) : array{
		if($portRange === null || $portRange === []){
			return [null, null];
		}
		if(!is_array($portRange) || count($portRange) !== 2 || !isset($portRange[0], $portRange[1])){
			throw new \InvalidArgumentException("port-range must be a list of two port numbers, e.g. [19132, 19140]");
		}

		$begin = $portRange[0];
		$end = $portRange[1];
		if(!is_int($begin) || !is_int($end)){
			throw new \InvalidArgumentException("port-range must contain two port numbers, e.g. [19132, 19140]");
		}
		if($begin < 1 || $begin > 65535 || $end < 1 || $end > 65535){
			throw new \InvalidArgumentException("port-range must be between 1 and 65535; leave it empty to use any available port");
		}
		if($begin > $end){
			throw new \InvalidArgumentException("port-range start $begin is above its end $end");
		}

		return [$begin, $end];
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	private static function parseServer(mixed $entry, string $where) : NetherNetIceServer{
		if(!is_array($entry)){
			throw new \InvalidArgumentException("$where must be a set of settings, got " . get_debug_type($entry));
		}

		$host = self::readString($entry, "host", $where);
		$port = self::DEFAULT_ICE_SERVER_PORT;
		if(isset($entry["port"])){
			$port = $entry["port"];
			if(!is_int($port)){
				throw new \InvalidArgumentException("$where has a \"port\" that is not a number");
			}
			if($port < 1 || $port > 65535){
				throw new \InvalidArgumentException("$where has a \"port\" outside the range 1-65535");
			}
		}

		$type = self::readString($entry, "type", $where);

		return match($type){
			"stun" => new NetherNetIceServer(false, $host, $port, "", ""),
			"turn" => new NetherNetIceServer(
				true,
				$host,
				$port,
				self::readString($entry, "username", $where),
				self::readString($entry, "password", $where)
			),
			default => throw new \InvalidArgumentException("$where has an unknown \"type\" \"$type\", expected \"stun\" or \"turn\"")
		};
	}

	/**
	 * @param mixed[] $entry
	 *
	 * @throws \InvalidArgumentException
	 */
	private static function readString(array $entry, string $key, string $where) : string{
		if(!isset($entry[$key])){
			throw new \InvalidArgumentException("$where is missing \"$key\"");
		}

		$value = $entry[$key];
		if(!is_string($value) || $value === ""){
			throw new \InvalidArgumentException("$where has a \"$key\" that is not a non-empty string, got " . get_debug_type($value));
		}

		return $value;
	}

	/**
	 * @return NetherNetIceServer[]
	 */
	public function getServers() : array{
		return (array) $this->servers;
	}

	public function isUdpMux() : bool{ return $this->udpMux; }

	public function getPortRangeBegin() : ?int{ return $this->portRangeBegin; }

	public function getPortRangeEnd() : ?int{ return $this->portRangeEnd; }
}
