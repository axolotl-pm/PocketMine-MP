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

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\VarInt;
use pmmp\thread\Thread as NativeThread;
use pmmp\thread\ThreadSafeArray;
use pmmp\webrtc\IceServer;
use pocketmine\nethernet\discovery\LanSignaling;
use pocketmine\nethernet\discovery\MutableServerDataProvider;
use pocketmine\nethernet\discovery\ServerData;
use pocketmine\nethernet\identity\AssertionIdentityVerifier;
use pocketmine\nethernet\identity\SelfSignedIdentityProvider;
use pocketmine\nethernet\identity\ServerIdentity;
use pocketmine\nethernet\negotiation\ConfiguredPeerConnectionFactory;
use pocketmine\nethernet\NetherNetException;
use pocketmine\nethernet\NetherNetServer;
use pocketmine\nethernet\ServerConfiguration;
use pocketmine\nethernet\signaling\http\HttpSignaling;
use pocketmine\nethernet\signaling\http\MutableServerStatusProvider;
use pocketmine\nethernet\signaling\http\ReverseProxy;
use pocketmine\nethernet\signaling\SignalingException;
use pocketmine\network\NetworkInterfaceStartException;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\thread\log\ThreadSafeLogger;
use pocketmine\thread\Thread;
use pocketmine\thread\ThreadCrashException;
use pocketmine\utils\Utils;
use function array_values;
use function get_debug_type;
use function is_array;
use function is_string;
use function microtime;
use function ord;
use function time_sleep_until;

final class NetherNetThread extends Thread{

	private const TPS = 100;
	private const TIME_PER_TICK = 1 / self::TPS;

	private const SHUTDOWN_DRAIN_TIMEOUT = 3.0;

	protected bool $ready = false;

	protected ?string $startupError = null;

	protected int $consumedBytes = 0;

	/**
	 * @phpstan-param ThreadSafeArray<int, string> $mainToThread
	 * @phpstan-param ThreadSafeArray<int, string> $threadToMain
	 * @phpstan-param ThreadSafeArray<int, string>|null $reverseProxyNetworks
	 */
	public function __construct(
		protected ThreadSafeLogger $logger,
		protected ThreadSafeArray $mainToThread,
		protected ThreadSafeArray $threadToMain,
		protected int $maxMtu,
		protected string $ip,
		protected int $port,
		protected string $identityPem,
		protected ?string $tlsCertFile,
		protected ?string $tlsKeyFile,
		protected ?int $lanPort,
		protected int $networkId,
		protected bool $allowAnonymous,
		protected NetherNetIceConfiguration $iceConfig,
		protected ?ThreadSafeArray $reverseProxyNetworks,
		protected SleeperHandlerEntry $sleeperEntry
	){}

	/**
	 * @phpstan-param list<string> $networks
	 */
	private static function createReverseProxy(array $networks) : ReverseProxy{
		return new ReverseProxy(
			[ReverseProxy::HEADER_FORWARDED_FOR, ReverseProxy::HEADER_REAL_IP, ReverseProxy::HEADER_CF_CONNECTING_IP],
			$networks
		);
	}

	/**
	 * @phpstan-return ThreadSafeArray<int, string>
	 *
	 * @throws \InvalidArgumentException
	 */
	public static function parseReverseProxyNetworks(mixed $trustedIps) : ThreadSafeArray{
		if($trustedIps === null){
			$trustedIps = [];
		}elseif(!is_array($trustedIps)){
			throw new \InvalidArgumentException("Trusted IPs must be a list of addresses or CIDR blocks, got " . get_debug_type($trustedIps));
		}

		$networks = [];
		foreach(Utils::promoteKeys($trustedIps) as $index => $entry){
			if(!is_string($entry) || $entry === ""){
				throw new \InvalidArgumentException("Trusted IPs entry $index must be an address or CIDR block, got " . get_debug_type($entry));
			}
			$networks[] = $entry;
		}
		self::createReverseProxy($networks);

		return ThreadSafeArray::fromArray($networks);
	}

	/**
	 * @throws NetworkInterfaceStartException
	 */
	public function startAndWait(int $options = NativeThread::INHERIT_NONE) : void{
		$this->start($options);
		$this->synchronized(function() : void{
			while(!$this->ready && !$this->isTerminated()){
				$this->wait();
			}
		});

		$crashInfo = $this->getCrashInfo();
		if($crashInfo !== null){
			throw new ThreadCrashException("NetherNet failed to start", $crashInfo);
		}
		if($this->startupError !== null){
			throw new NetworkInterfaceStartException($this->startupError);
		}
	}

	public function setConsumedBytes(int $bytes) : void{
		$this->consumedBytes = $bytes;
	}

	protected function onRun() : void{
		\GlobalLogger::set($this->logger);

		$in = new NetherNetChannel($this->mainToThread);
		$out = new NetherNetChannel($this->threadToMain, $this->sleeperEntry->createNotifier());

		$listener = new NetherNetSessionListener($out);
		$advert = new MutableServerDataProvider(new ServerData(serverName: "", levelName: ""));
		$status = new MutableServerStatusProvider();

		try{
			try{
				$server = $this->createServer($listener, $advert, $status, $this->lanPort);
				$server->start();
			}catch(SignalingException $e){
				if($this->lanPort === null){
					throw $e;
				}

				$this->logger->warning("Unable to start LAN discovery");
				$server = $this->createServer($listener, $advert, $status, null);
				$server->start();
			}
		}catch(NetherNetException|\InvalidArgumentException $e){
			$this->synchronized(function() use ($e) : void{
				$this->startupError = $e->getMessage();
				$this->ready = true;
				$this->notify();
			});

			return;
		}

		$this->synchronized(function() : void{
			$this->ready = true;
			$this->notify();
		});

		while(!$this->isKilled){
			$start = microtime(true);

			$listener->setConsumedBytes($this->consumedBytes);
			$server->tick();

			$this->handleInbound($in, $listener, $advert, $status);
			$listener->flushReceipts();

			self::sleepUntilNextTick($start);
		}
		$this->handleInbound($in, $listener, $advert, $status);

		$deadline = microtime(true) + self::SHUTDOWN_DRAIN_TIMEOUT;
		while($server->getSessionManager()->count() > 0 && microtime(true) < $deadline){
			$start = microtime(true);

			$server->tick();

			self::sleepUntilNextTick($start);
		}

		$server->shutdown();
	}

	/**
	 * @throws NetherNetException
	 * @throws \InvalidArgumentException
	 */
	private function createServer(NetherNetSessionListener $listener, MutableServerDataProvider $advert, MutableServerStatusProvider $status, ?int $lanPort) : NetherNetServer{
		$iceServers = [];
		foreach($this->iceConfig->getServers() as $iceServer){
			$iceServers[] = $iceServer->isTurn()
				? IceServer::turn($iceServer->getHost(), $iceServer->getPort(), $iceServer->getUsername(), $iceServer->getPassword())
				: IceServer::stun($iceServer->getHost(), $iceServer->getPort());
		}

		$server = NetherNetServer::create(
			new ServerConfiguration(
				identityProvider: new SelfSignedIdentityProvider(ServerIdentity::fromPrivateKeyPem($this->identityPem)),
				identityVerifier: new AssertionIdentityVerifier(allowAnonymous: $this->allowAnonymous),
				peerConnectionFactory: new ConfiguredPeerConnectionFactory(
					iceServers: $iceServers,
					portRangeBegin: $this->iceConfig->getPortRangeBegin(),
					portRangeEnd: $this->iceConfig->getPortRangeEnd(),
					iceUdpMuxEnabled: $this->iceConfig->isUdpMux(),
					mtu: $this->maxMtu
				),
				logger: $this->logger
			),
			$listener
		);

		$tlsContext = $this->tlsCertFile !== null && $this->tlsKeyFile !== null
			? ["local_cert" => $this->tlsCertFile, "local_pk" => $this->tlsKeyFile]
			: null;
		$reverseProxy = $this->reverseProxyNetworks !== null ? self::createReverseProxy(array_values((array) $this->reverseProxyNetworks)) : null;
		$server->addSignaling(new HttpSignaling(
			$server->getNegotiator(),
			$this->ip,
			$this->port,
			$tlsContext,
			$this->logger,
			HttpSignaling::DEFAULT_MAX_CONNECTIONS,
			$status,
			$reverseProxy
		));

		if($lanPort !== null){
			$server->addSignaling(new LanSignaling(
				$server->getNegotiator(),
				$advert,
				$this->networkId,
				"0.0.0.0",
				$lanPort,
				$this->logger
			));
		}

		return $server;
	}

	private static function sleepUntilNextTick(float $start) : void{
		if(microtime(true) - $start < self::TIME_PER_TICK){
			@time_sleep_until($start + self::TIME_PER_TICK);
		}
	}

	private function handleInbound(NetherNetChannel $in, NetherNetSessionListener $listener, MutableServerDataProvider $advert, MutableServerStatusProvider $status) : void{
		while(($message = $in->read()) !== null){
			$reader = new ByteBufferReader($message);
			$type = ord($reader->readByteArray(1));

			if($type === NetherNetIpc::M2T_SET_SERVER_DATA){
				$pong = $reader->readByteArray($reader->getUnreadLength());
				$data = ServerData::fromPongData($pong);
				if($data !== null){
					$advert->setServerData($data);
				}
				$status->setPongData($pong);
				continue;
			}

			$sessionId = VarInt::readUnsignedInt($reader);
			if($type === NetherNetIpc::M2T_CLOSE_SESSION){
				$listener->close($sessionId);
				continue;
			}

			// 0 means no receipt requested; valid receipt IDs are incremented by 1
			$receiptId = VarInt::readUnsignedInt($reader);
			$listener->send(
				$sessionId,
				$reader->readByteArray($reader->getUnreadLength()),
				$receiptId === 0 ? null : $receiptId - 1
			);
		}
	}

	public function getThreadName() : string{
		return "NetherNet";
	}
}
