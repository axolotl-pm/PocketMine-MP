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
use pocketmine\nethernet\discovery\MutableServerDataProvider;
use pocketmine\nethernet\discovery\ServerData;
use pocketmine\nethernet\identity\AssertionIdentityVerifier;
use pocketmine\nethernet\identity\SelfSignedIdentityProvider;
use pocketmine\nethernet\identity\ServerIdentity;
use pocketmine\nethernet\negotiation\ConfiguredPeerConnectionFactory;
use pocketmine\nethernet\NetherNetException;
use pocketmine\nethernet\NetherNetServer;
use pocketmine\nethernet\SctpConfiguration;
use pocketmine\nethernet\ServerConfiguration;
use pocketmine\nethernet\signaling\http\MutableServerStatusProvider;
use pocketmine\network\NetworkInterfaceStartException;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\thread\log\ThreadSafeLogger;
use pocketmine\thread\Thread;
use pocketmine\thread\ThreadCrashException;
use function microtime;
use function ord;
use function time_sleep_until;

final class NetherNetThread extends Thread{

	private const TPS = 100;
	private const TIME_PER_TICK = 1 / self::TPS;

	private const SHUTDOWN_DRAIN_TIMEOUT = 3.0;

	/**
	 * Interval in milliseconds between SCTP heartbeats.
	 */
	private const SCTP_HEARTBEAT_INTERVAL = 750;
	/**
	 * Maximum number of retransmit attempts for SCTP
	 * Clients will be disconnected after this threshold is reached.
	 */
	private const SCTP_MAX_RETRANSMIT_ATTEMPTS = 4;

	protected bool $ready = false;

	protected ?string $startupError = null;

	protected int $consumedBytes = 0;

	protected int $ticks = 0;

	/** @phpstan-var ThreadSafeArray<int, NetherNetSignalingFactory> */
	protected ThreadSafeArray $signalingFactories;

	/**
	 * @phpstan-param ThreadSafeArray<int, string>                    $mainToThread
	 * @phpstan-param ThreadSafeArray<int, string>                    $threadToMain
	 * @phpstan-param ThreadSafeArray<int, NetherNetSignalingFactory> $signalingFactories
	 */
	public function __construct(
		protected ThreadSafeLogger $logger,
		protected ThreadSafeArray $mainToThread,
		protected ThreadSafeArray $threadToMain,
		protected int $maxMtu,
		protected string $identityPem,
		protected string $identityDomain,
		protected bool $allowAnonymous,
		protected NetherNetIceConfiguration $iceConfig,
		ThreadSafeArray $signalingFactories,
		protected SleeperHandlerEntry $sleeperEntry
	){
		$this->signalingFactories = $signalingFactories;
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
			[$server, $ipcSignaling] = $this->createServer($listener, $out, $advert, $status);
			$server->start();
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

			$this->handleInbound($in, $server, $listener, $ipcSignaling, $advert, $status);
			$listener->flushReceipts();
			$listener->updateBandwidthStats();
			if(++$this->ticks % self::TPS === 0){
				$listener->flushBandwidthStats();
				$listener->flushPings();
			}

			self::sleepUntilNextTick($start);
		}
		$this->handleInbound($in, $server, $listener, $ipcSignaling, $advert, $status);

		$deadline = microtime(true) + self::SHUTDOWN_DRAIN_TIMEOUT;
		while($server->getSessionManager()->count() > 0 && microtime(true) < $deadline){
			$start = microtime(true);

			$server->tick();

			self::sleepUntilNextTick($start);
		}

		$server->shutdown();
	}

	/**
	 * @phpstan-return array{NetherNetServer, NetherNetIpcSignaling}
	 *
	 * @throws NetherNetException
	 * @throws \InvalidArgumentException
	 */
	private function createServer(NetherNetSessionListener $listener, NetherNetChannel $out, MutableServerDataProvider $advert, MutableServerStatusProvider $status) : array{
		$iceServers = [];
		foreach($this->iceConfig->getServers() as $iceServer){
			$iceServers[] = $iceServer->isTurn()
				? IceServer::turn($iceServer->getHost(), $iceServer->getPort(), $iceServer->getUsername(), $iceServer->getPassword())
				: IceServer::stun($iceServer->getHost(), $iceServer->getPort());
		}

		$server = NetherNetServer::create(
			new ServerConfiguration(
				identityProvider: new SelfSignedIdentityProvider(ServerIdentity::fromPrivateKeyPem($this->identityPem), $this->identityDomain),
				identityVerifier: new AssertionIdentityVerifier(allowAnonymous: $this->allowAnonymous),
				peerConnectionFactory: new ConfiguredPeerConnectionFactory(
					iceServers: $iceServers,
					bindAddress: $this->iceConfig->getBindAddress(),
					portRangeBegin: $this->iceConfig->getPortRangeBegin(),
					portRangeEnd: $this->iceConfig->getPortRangeEnd(),
					iceUdpMuxEnabled: $this->iceConfig->isUdpMux(),
					mtu: $this->maxMtu
				),
				logger: $this->logger,
				sctp: new SctpConfiguration(
					heartbeatInterval: self::SCTP_HEARTBEAT_INTERVAL,
					maxRetransmitAttempts: self::SCTP_MAX_RETRANSMIT_ATTEMPTS
				)
			),
			$listener
		);

		$context = new NetherNetSignalingContext($server->getNegotiator(), $status, $advert, $this->logger);
		foreach((array) $this->signalingFactories as $factory){
			foreach($factory->create($context) as $signaling){
				$server->addSignaling($signaling);
			}
		}

		$ipcSignaling = new NetherNetIpcSignaling($server->getNegotiator(), $out, $this->logger);
		$server->addSignaling($ipcSignaling);

		return [$server, $ipcSignaling];
	}

	private static function sleepUntilNextTick(float $start) : void{
		if(microtime(true) - $start < self::TIME_PER_TICK){
			@time_sleep_until($start + self::TIME_PER_TICK);
		}
	}

	private function handleInbound(NetherNetChannel $in, NetherNetServer $server, NetherNetSessionListener $listener, NetherNetIpcSignaling $ipcSignaling, MutableServerDataProvider $advert, MutableServerStatusProvider $status) : void{
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

			if($type === NetherNetIpc::M2T_BLOCK_ADDRESS){
				$address = $reader->readByteArray(VarInt::readUnsignedInt($reader));
				$timeout = VarInt::readSignedInt($reader);
				$server->blockAddress($address, $timeout);
				continue;
			}

			if($type === NetherNetIpc::M2T_UNBLOCK_ADDRESS){
				$address = $reader->readByteArray(VarInt::readUnsignedInt($reader));
				$server->unblockAddress($address);
				continue;
			}

			if($type === NetherNetIpc::M2T_OFFER){
				$requestId = VarInt::readUnsignedInt($reader);
				$networkId = $reader->readByteArray(VarInt::readUnsignedInt($reader));
				$clientAddress = $reader->readByteArray(VarInt::readUnsignedInt($reader));
				$ipcSignaling->acceptOffer(
					$requestId,
					$networkId,
					$reader->readByteArray($reader->getUnreadLength()),
					$clientAddress === "" ? null : $clientAddress
				);
				continue;
			}

			if($type === NetherNetIpc::M2T_CANCEL_OFFER){
				$ipcSignaling->cancel(VarInt::readUnsignedInt($reader));
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
