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
use pmmp\thread\ThreadSafeArray;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\lang\Translatable;
use pocketmine\nethernet\negotiation\ErrorCode;
use pocketmine\nethernet\session\DisconnectReason;
use pocketmine\nethernet\signaling\http\HttpSignaling;
use pocketmine\nethernet\signaling\http\ServerStatus;
use pocketmine\network\AdvancedNetworkInterface;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\ServerPongData;
use pocketmine\network\Network;
use pocketmine\network\PacketHandlingException;
use pocketmine\Server;
use pocketmine\thread\ThreadCrashException;
use pocketmine\timings\Timings;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Utils;
use pocketmine\YmlServerProperties;
use function bin2hex;
use function filter_var;
use function implode;
use function ord;
use function strlen;
use const FILTER_VALIDATE_IP;

final class NetherNetInterface implements AdvancedNetworkInterface{

	private const CONSUMPTION_REPORT_INTERVAL = 1024;

	private NetherNetThread $thread;

	private NetherNetChannel $toThread;

	private NetherNetChannel $fromThread;

	private int $sleeperNotifierId;

	/** @var array<int, NetworkSession> */
	private array $sessions = [];

	/** @phpstan-var array<int, NetherNetNegotiation> */
	private array $pendingOffers = [];

	private int $nextOfferId = 0;

	private string $name = "";

	private bool $shutDown = false;

	private Network $network;

	/**
	 * @phpstan-param ThreadSafeArray<int, NetherNetSignalingFactory> $signalingFactories
	 */
	public function __construct(
		private Server $server,
		string $identityPem,
		private int $networkId,
		NetherNetIceConfiguration $iceConfig,
		private ThreadSafeArray $signalingFactories,
		private PacketBroadcaster $packetBroadcaster,
		private EntityEventBroadcaster $entityEventBroadcaster,
		private TypeConverter $typeConverter
	){
		$sleeperEntry = $this->server->getTickSleeper()->addNotifier(function() : void{
			Timings::$connection->startTiming();
			try{
				$handled = 0;
				while($this->handleMessage()){
					if(++$handled % self::CONSUMPTION_REPORT_INTERVAL === 0){
						$this->thread->setConsumedBytes($this->fromThread->getTotalBytes());
					}
				}
				$this->thread->setConsumedBytes($this->fromThread->getTotalBytes());
			}finally{
				Timings::$connection->stopTiming();
			}
		});
		$this->sleeperNotifierId = $sleeperEntry->getNotifierId();

		/** @phpstan-var ThreadSafeArray<int, string> $mainToThread */
		$mainToThread = new ThreadSafeArray();
		/** @phpstan-var ThreadSafeArray<int, string> $threadToMain */
		$threadToMain = new ThreadSafeArray();

		$this->toThread = new NetherNetChannel($mainToThread);
		$this->fromThread = new NetherNetChannel($threadToMain);

		$this->thread = new NetherNetThread(
			$this->server->getLogger(),
			$mainToThread,
			$threadToMain,
			$this->server->getConfigGroup()->getPropertyInt(YmlServerProperties::NETWORK_MAX_MTU_SIZE, 1492),
			$identityPem,
			!$this->server->getOnlineMode(),
			$iceConfig,
			$signalingFactories,
			$sleeperEntry
		);
	}

	public function start() : void{
		$this->server->getLogger()->debug("Waiting for NetherNet to start...");
		$this->thread->startAndWait();
		$this->server->getLogger()->debug("NetherNet booted successfully");

		foreach((array) $this->signalingFactories as $factory){
			foreach($factory->getStartupMessages() as $message){
				$this->server->getLogger()->info($message instanceof Translatable ? $this->server->getLanguage()->translate($message) : $message);
			}
		}
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	public function acceptOffer(string $networkId, string $offerSdp, ?string $clientAddress = null) : NetherNetNegotiation{
		if($networkId === "" || strlen($networkId) > HttpSignaling::MAX_NETWORK_ID_LENGTH){
			throw new \InvalidArgumentException("Network ID must be between 1 and " . HttpSignaling::MAX_NETWORK_ID_LENGTH . " bytes long");
		}
		if($clientAddress !== null && filter_var($clientAddress, FILTER_VALIDATE_IP) === false){
			throw new \InvalidArgumentException("Client address must be a valid IP address, got \"$clientAddress\"");
		}

		if($this->shutDown){
			$negotiation = new NetherNetNegotiation($networkId, $clientAddress, static function() : void{});
			$negotiation->fail(ErrorCode::NO_SIGNALING_CHANNEL, "NetherNet is shut down");

			return $negotiation;
		}

		$requestId = $this->nextOfferId++;
		$negotiation = new NetherNetNegotiation($networkId, $clientAddress, function() use ($requestId) : void{
			if(isset($this->pendingOffers[$requestId])){
				unset($this->pendingOffers[$requestId]);
				$this->toThread->write(NetherNetIpc::cancelOffer($requestId));
			}
		});

		$this->pendingOffers[$requestId] = $negotiation;
		$this->toThread->write(NetherNetIpc::offer($requestId, $networkId, $clientAddress, $offerSdp));

		return $negotiation;
	}

	public function getServerStatus() : ServerStatus{
		return ServerStatus::fromPongData(ServerPongData::build($this->server, $this->name, $this->networkId))
			?? throw new AssumptionFailedError("Pong data built by the server is always parseable");
	}

	public function tick() : void{
		if(!$this->thread->isRunning()){
			$crashInfo = $this->thread->getCrashInfo();
			if($crashInfo !== null){
				throw new ThreadCrashException("NetherNet crashed", $crashInfo);
			}
			throw new \RuntimeException("NetherNet thread crashed without crash information");
		}
	}

	private function handleMessage() : bool{
		$message = $this->fromThread->read();
		if($message === null){
			return false;
		}

		$reader = new ByteBufferReader($message);
		$type = ord($reader->readByteArray(1));
		$id = VarInt::readUnsignedInt($reader);

		switch($type){
			case NetherNetIpc::T2M_SESSION_OPEN:
				$address = $reader->readByteArray(VarInt::readUnsignedInt($reader));
				$port = VarInt::readUnsignedInt($reader);
				$publicKeyDigest = $reader->readByteArray($reader->getUnreadLength());
				$this->openSession($id, $address, $port, $publicKeyDigest);
				break;

			case NetherNetIpc::T2M_PACKET:
				$this->handlePacket($id, $reader->readByteArray($reader->getUnreadLength()));
				break;

			case NetherNetIpc::T2M_SESSION_CLOSE:
				$reason = DisconnectReason::tryFrom(VarInt::readUnsignedInt($reader));
				$session = $this->sessions[$id] ?? null;
				unset($this->sessions[$id]);
				$session?->onClientDisconnect(self::disconnectReason($reason));
				break;

			case NetherNetIpc::T2M_RECEIPT:
				($this->sessions[$id] ?? null)?->handleAckReceipt(VarInt::readUnsignedInt($reader));
				break;

			case NetherNetIpc::T2M_BANDWIDTH_STATS:
				$bytesSentDiff = VarInt::readUnsignedLong($reader);
				$bytesReceivedDiff = VarInt::readUnsignedLong($reader);
				$this->network->getBandwidthTracker()->add($bytesSentDiff, $bytesReceivedDiff);
				break;

			case NetherNetIpc::T2M_PING:
				($this->sessions[$id] ?? null)?->updatePing(VarInt::readUnsignedInt($reader));
				break;

			case NetherNetIpc::T2M_OFFER_ANSWER:
				$negotiation = $this->pendingOffers[$id] ?? null;
				unset($this->pendingOffers[$id]);
				$negotiation?->resolve($reader->readByteArray($reader->getUnreadLength()));
				break;

			case NetherNetIpc::T2M_OFFER_FAILURE:
				$negotiation = $this->pendingOffers[$id] ?? null;
				unset($this->pendingOffers[$id]);
				$code = ErrorCode::tryFrom(VarInt::readUnsignedInt($reader)) ?? ErrorCode::GENERIC_FAILURE;
				$negotiation?->fail($code, $reader->readByteArray($reader->getUnreadLength()));
				break;

			default:
				$this->server->getLogger()->debug("Unknown NetherNet IPC message type $type");
				break;
		}

		return true;
	}

	public function setNetwork(Network $network) : void{
		$this->network = $network;
	}

	private static function disconnectReason(?DisconnectReason $reason) : Translatable|string{
		return match($reason){
			DisconnectReason::PEER_DISCONNECT => KnownTranslationFactory::pocketmine_disconnect_clientDisconnect(),
			DisconnectReason::CONNECTION_FAILED => KnownTranslationFactory::pocketmine_disconnect_error_timeout(),
			DisconnectReason::BAD_DATA => KnownTranslationFactory::pocketmine_disconnect_error_badPacket(),
			null => "Unknown NetherNet disconnect reason",
			default => "NetherNet: " . $reason->getMessage()
		};
	}

	private function openSession(int $sessionId, string $address, int $port, string $publicKeyDigest) : void{
		$session = new NetworkSession(
			$this->server,
			$this->network->getSessionManager(),
			PacketPool::getInstance(),
			new NetherNetPacketSender($sessionId, $this),
			$this->packetBroadcaster,
			$this->entityEventBroadcaster,
			ZlibCompressor::getInstance(),
			$this->typeConverter,
			$address,
			$port,
			new NetherNetIdentityKey($publicKeyDigest !== "" ? $publicKeyDigest : null)
		);
		$this->sessions[$sessionId] = $session;

		if($publicKeyDigest !== ""){
			$session->getLogger()->debug("NetherNet identity key " . bin2hex($publicKeyDigest));
		}
	}

	private function handlePacket(int $sessionId, string $payload) : void{
		$session = $this->sessions[$sessionId] ?? null;
		if($session === null){
			return;
		}

		$name = $session->getDisplayName();
		try{
			$session->handleEncoded($payload);
		}catch(PacketHandlingException $e){
			$session->disconnectWithError(
				reason: "Bad packet: " . $e->getMessage(),
				disconnectScreenMessage: KnownTranslationFactory::pocketmine_disconnect_error_badPacket()
			);
			$session->getLogger()->debug(implode("\n", Utils::printableExceptionInfo($e)));
		}catch(\Throwable $e){
			$this->server->getLogger()->emergency("Crash occurred while handling a packet from session: $name");
			throw $e;
		}
	}

	public function putPacket(int $sessionId, string $payload, ?int $receiptId = null) : void{
		if(isset($this->sessions[$sessionId])){
			$this->toThread->write(NetherNetIpc::send($sessionId, $payload, $receiptId));
		}
	}

	public function close(int $sessionId) : void{
		if(isset($this->sessions[$sessionId])){
			unset($this->sessions[$sessionId]);
			$this->toThread->write(NetherNetIpc::closeSession($sessionId));
		}
	}

	public function setName(string $name) : void{
		$this->name = $name;
		$this->toThread->write(NetherNetIpc::setServerData(ServerPongData::build($this->server, $name, $this->networkId)));
	}

	public function shutdown() : void{
		$this->shutDown = true;
		$pendingOffers = $this->pendingOffers;
		$this->pendingOffers = [];
		foreach($pendingOffers as $negotiation){
			$negotiation->fail(ErrorCode::NO_SIGNALING_CHANNEL, "NetherNet is shutting down");
		}

		$this->server->getTickSleeper()->removeNotifier($this->sleeperNotifierId);
		$this->thread->quit();
	}

	public function blockAddress(string $address, int $timeout = 300) : void{
		$this->toThread->write(NetherNetIpc::blockAddress($address, $timeout));
	}

	public function unblockAddress(string $address) : void{
		$this->toThread->write(NetherNetIpc::unblockAddress($address));
	}

	public function sendRawPacket(string $address, int $port, string $payload) : void{}

	public function addRawPacketFilter(string $regex) : void{}
}
