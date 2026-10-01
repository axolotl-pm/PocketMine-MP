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

use pmmp\thread\ThreadSafeArray;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\nethernet\discovery\LanSignaling;
use pocketmine\nethernet\signaling\http\HttpSignaling;
use pocketmine\nethernet\signaling\http\ReverseProxy;
use function array_values;

final class NetherNetBuiltinSignalingFactory extends NetherNetSignalingFactory{

	/** @phpstan-var ThreadSafeArray<int, string>|null */
	private ?ThreadSafeArray $reverseProxyNetworks;

	/**
	 * @phpstan-param list<string>|null $reverseProxyNetworks
	 *
	 * @throws \InvalidArgumentException if a network cannot be parsed
	 */
	public function __construct(
		private string $httpBindAddress,
		private int $httpPort,
		private ?string $tlsCertFile,
		private ?string $tlsKeyFile,
		private ?string $tlsPassphrase,
		?array $reverseProxyNetworks,
		private string $lanBindAddress,
		private int $lanPort,
		private int $networkId
	){
		if($reverseProxyNetworks !== null){
			self::createReverseProxy($reverseProxyNetworks);
		}
		$this->reverseProxyNetworks = $reverseProxyNetworks === null ? null : ThreadSafeArray::fromArray($reverseProxyNetworks);
	}

	/**
	 * @phpstan-param list<string> $networks
	 *
	 * @throws \InvalidArgumentException
	 */
	private static function createReverseProxy(array $networks) : ReverseProxy{
		return new ReverseProxy(
			[ReverseProxy::HEADER_FORWARDED_FOR, ReverseProxy::HEADER_REAL_IP, ReverseProxy::HEADER_CF_CONNECTING_IP],
			$networks
		);
	}

	public function create(NetherNetSignalingContext $context) : array{
		$tlsContext = null;
		if($this->tlsCertFile !== null && $this->tlsKeyFile !== null){
			$tlsContext = ["local_cert" => $this->tlsCertFile, "local_pk" => $this->tlsKeyFile];
			if($this->tlsPassphrase !== null){
				$tlsContext["passphrase"] = $this->tlsPassphrase;
			}
		}

		return [
			new HttpSignaling(
				$context->getNegotiator(),
				$this->httpBindAddress,
				$this->httpPort,
				$tlsContext,
				$context->getLogger(),
				HttpSignaling::DEFAULT_MAX_CONNECTIONS,
				$context->getStatusProvider(),
				$this->reverseProxyNetworks !== null ? self::createReverseProxy(array_values((array) $this->reverseProxyNetworks)) : null
			),
			new NetherNetOptionalSignaling(
				new LanSignaling(
					$context->getNegotiator(),
					$context->getServerDataProvider(),
					$this->networkId,
					$this->lanBindAddress,
					$this->lanPort,
					$context->getLogger()
				),
				"Unable to start LAN discovery",
				$context->getLogger()
			),
		];
	}

	public function getStartupMessages() : array{
		return [KnownTranslationFactory::pocketmine_server_nethernet_signalingStart($this->httpBindAddress, (string) $this->httpPort, "TCP")];
	}
}
