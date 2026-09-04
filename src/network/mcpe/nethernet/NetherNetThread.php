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
use pocketmine\nethernet\discovery\LanSignaling;
use pocketmine\nethernet\discovery\MutableServerDataProvider;
use pocketmine\nethernet\discovery\ServerData;
use pocketmine\nethernet\identity\AssertionIdentityVerifier;
use pocketmine\nethernet\identity\SelfSignedIdentityProvider;
use pocketmine\nethernet\identity\ServerIdentity;
use pocketmine\nethernet\NetherNetException;
use pocketmine\nethernet\NetherNetServer;
use pocketmine\nethernet\ServerConfiguration;
use pocketmine\nethernet\signaling\http\HttpSignaling;
use pocketmine\nethernet\signaling\http\MutableServerStatusProvider;
use pocketmine\nethernet\signaling\SignalingException;
use pocketmine\network\NetworkInterfaceStartException;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\thread\log\ThreadSafeLogger;
use pocketmine\thread\Thread;
use pocketmine\thread\ThreadCrashException;
use function ord;
use function usleep;

class NetherNetThread extends Thread{

	/** Sleep interval in microseconds between poll loops (5ms). */
	private const POLL_INTERVAL_US = 5000;

	protected bool $ready = false;

	protected ?string $startupError = null;

	/**
	 * @param string   $identityPem    Server identity private key in PEM format.
	 * @param int|null $lanPort        UDP port for LAN discovery, or null to disable it.
	 * @param int      $networkId      Unique network ID for LAN discovery.
	 * @param bool     $allowAnonymous Whether to accept peers that present no identity assertion.
	 *
	 * @phpstan-param ThreadSafeArray<int, string> $mainToThread
	 * @phpstan-param ThreadSafeArray<int, string> $threadToMain
	 */
	public function __construct(
		protected ThreadSafeLogger $logger,
		protected ThreadSafeArray $mainToThread,
		protected ThreadSafeArray $threadToMain,
		protected string $ip,
		protected int $port,
		protected string $identityPem,
		protected ?string $tlsCertFile,
		protected ?string $tlsKeyFile,
		protected ?int $lanPort,
		protected int $networkId,
		protected bool $allowAnonymous,
		protected SleeperHandlerEntry $sleeperEntry
	){}

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

				// LAN discovery port may conflict with a local Minecraft client; continue without LAN discovery
				$this->logger->warning("LAN discovery could not start (" . $e->getMessage() . "); continuing without it");
				$server = $this->createServer($listener, $advert, $status, null);
				$server->start();
			}
		}catch(NetherNetException $e){
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
			$server->tick();

			$this->handleInbound($in, $listener, $advert, $status);
			$listener->flushReceipts();

			usleep(self::POLL_INTERVAL_US);
		}

		$server->shutdown();
	}

	/**
	 * @throws NetherNetException
	 */
	private function createServer(NetherNetSessionListener $listener, MutableServerDataProvider $advert, MutableServerStatusProvider $status, ?int $lanPort) : NetherNetServer{
		$server = NetherNetServer::create(
			new ServerConfiguration(
				identityProvider: new SelfSignedIdentityProvider(ServerIdentity::fromPrivateKeyPem($this->identityPem)),
				identityVerifier: new AssertionIdentityVerifier(allowAnonymous: $this->allowAnonymous),
				logger: $this->logger
			),
			$listener
		);

		$tlsContext = $this->tlsCertFile !== null && $this->tlsKeyFile !== null
			? ["local_cert" => $this->tlsCertFile, "local_pk" => $this->tlsKeyFile]
			: null;
		$server->addSignaling(new HttpSignaling($server->getNegotiator(), $this->ip, $this->port, $tlsContext, $this->logger, statusProvider: $status));

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
