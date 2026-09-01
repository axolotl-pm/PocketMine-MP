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
use pocketmine\nethernet\crypto\CryptoException;
use pocketmine\nethernet\discovery\LanSignaling;
use pocketmine\nethernet\identity\ServerIdentity;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\ServerPongData;
use pocketmine\network\NetworkInterface;
use pocketmine\network\NetworkInterfaceStartException;
use pocketmine\network\PacketHandlingException;
use pocketmine\Server;
use pocketmine\thread\ThreadCrashException;
use pocketmine\timings\Timings;
use pocketmine\utils\Utils;
use Symfony\Component\Filesystem\Path;
use function bin2hex;
use function chmod;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function implode;
use function is_dir;
use function is_file;
use function mkdir;
use function ord;
use function substr;
use function umask;
use function unpack;
use const PHP_INT_MAX;

/**
 * TODO: Translations
 */
class NetherNetInterface implements NetworkInterface{

	private const TLS_CERT_FILE = "nethernet-cert.pem";
	private const TLS_KEY_FILE = "nethernet-key.pem";

	private NetherNetThread $thread;

	private NetherNetChannel $toThread;

	private NetherNetChannel $fromThread;

	private int $sleeperNotifierId;

	private int $networkId;

	/** @var array<int, NetworkSession> */
	private array $sessions = [];

	/**
	 * @throws NetworkInterfaceStartException if the identity key cannot be prepared
	 */
	public function __construct(
		private Server $server,
		string $ip,
		int $port,
		private string $identityKeyFile,
		private PacketBroadcaster $packetBroadcaster,
		private EntityEventBroadcaster $entityEventBroadcaster,
		private TypeConverter $typeConverter
	){
		$sleeperEntry = $this->server->getTickSleeper()->addNotifier(function() : void{
			Timings::$connection->startTiming();
			try{
				while($this->handleMessage());
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

		[$certificate, $key] = $this->findTlsFiles();

		$identityPem = $this->loadOrCreateIdentityPem();
		$this->networkId = self::networkIdOf($identityPem);

		$this->thread = new NetherNetThread(
			$this->server->getLogger(),
			$mainToThread,
			$threadToMain,
			$ip,
			$port,
			$identityPem,
			$certificate,
			$key,
			LanSignaling::DEFAULT_PORT,
			$this->networkId,
			!$this->server->getOnlineMode(),
			$sleeperEntry
		);
	}

	public function start() : void{
		$this->server->getLogger()->debug("Waiting for NetherNet to start...");
		$this->thread->startAndWait();
		$this->server->getLogger()->debug("NetherNet booted successfully");
	}

	/**
	 * Loads the NetherNet server identity private key from disk, or generates and saves a new one.
	 *
	 * @throws NetworkInterfaceStartException
	 */
	private function loadOrCreateIdentityPem() : string{
		$path = Path::makeAbsolute($this->identityKeyFile, $this->server->getDataPath());

		if(is_file($path)){
			$pem = file_get_contents($path);
			if($pem === false){
				throw new NetworkInterfaceStartException("The NetherNet identity key at $path exists but could not be read");
			}
			try{
				ServerIdentity::fromPrivateKeyPem($pem);
			}catch(CryptoException $e){
				throw new NetworkInterfaceStartException(
					"The NetherNet identity key at $path is not a valid private key: " . $e->getMessage() .
					". Fix or remove the file; removing it generates a fresh identity, which prompts returning players to trust this server again.",
					0,
					$e
				);
			}

			return $pem;
		}

		try{
			$pem = ServerIdentity::generate()->exportPrivateKeyPem();
		}catch(CryptoException $e){
			throw new NetworkInterfaceStartException("Could not create a NetherNet identity: " . $e->getMessage(), 0, $e);
		}

		$directory = dirname($path);
		if(!is_dir($directory)){
			@mkdir($directory, 0700, true);
		}

		$previousUmask = umask(0077);
		try{
			$written = file_put_contents($path, $pem);
		}finally{
			umask($previousUmask);
		}
		if($written === false){
			$this->server->getLogger()->warning("Could not save the NetherNet identity key to $path; players will be prompted again after a restart");
		}else{
			@chmod($path, 0600);
		}

		return $pem;
	}

	/**
	 * Locates TLS certificate and key files in the data directory if present.
	 *
	 * @return array{?string, ?string}
	 */
	private function findTlsFiles() : array{
		$certificate = Path::join($this->server->getDataPath(), self::TLS_CERT_FILE);
		$key = Path::join($this->server->getDataPath(), self::TLS_KEY_FILE);

		if(!is_file($certificate) || !is_file($key)){
			$this->server->getLogger()->notice("Serving NetherNet signaling over plain HTTP; drop a CA-signed " . self::TLS_CERT_FILE . " and " . self::TLS_KEY_FILE . " into the data directory to use HTTPS");
			return [null, null];
		}

		return [$certificate, $key];
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
		$sessionId = VarInt::readUnsignedInt($reader);

		switch($type){
			case NetherNetIpc::T2M_SESSION_OPEN:
				$address = $reader->readByteArray(VarInt::readUnsignedInt($reader));
				$port = VarInt::readUnsignedInt($reader);
				$publicKeyDigest = $reader->readByteArray($reader->getUnreadLength());
				$this->openSession($sessionId, $address, $port, $publicKeyDigest);
				break;

			case NetherNetIpc::T2M_PACKET:
				$this->handlePacket($sessionId, $reader->readByteArray($reader->getUnreadLength()));
				break;

			case NetherNetIpc::T2M_SESSION_CLOSE:
				$reason = $reader->readByteArray($reader->getUnreadLength());
				$session = $this->sessions[$sessionId] ?? null;
				unset($this->sessions[$sessionId]);
				$session?->onClientDisconnect($reason === "" ? KnownTranslationFactory::pocketmine_disconnect_clientDisconnect() : "NetherNet: $reason");
				break;

			case NetherNetIpc::T2M_RECEIPT:
				($this->sessions[$sessionId] ?? null)?->handleAckReceipt(VarInt::readUnsignedInt($reader));
				break;
		}

		return true;
	}

	private function openSession(int $sessionId, string $address, int $port, string $publicKeyDigest) : void{
		$session = new NetworkSession(
			$this->server,
			$this->server->getNetwork()->getSessionManager(),
			PacketPool::getInstance(),
			new NetherNetPacketSender($sessionId, $this),
			$this->packetBroadcaster,
			$this->entityEventBroadcaster,
			ZlibCompressor::getInstance(),
			$this->typeConverter,
			$address,
			$port,
			true //NetherNet does not use encryption
		);
		$this->sessions[$sessionId] = $session;

		if($publicKeyDigest !== ""){
			//TODO: verify this key digest against the client's public key in the LoginPacket
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
			$logger = $session->getLogger();

			$session->disconnectWithError(
				reason: "Bad packet: " . $e->getMessage(),
				disconnectScreenMessage: KnownTranslationFactory::pocketmine_disconnect_error_badPacket()
			);
			//intentionally doesn't use logException, we don't want spammy packet error traces to appear in release mode
			$logger->debug(implode("\n", Utils::printableExceptionInfo($e)));
		}catch(\Throwable $e){
			//record the name of the player who caused the crash, to make it easier to find the reproducing steps
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
		$this->toThread->write(NetherNetIpc::setServerData(ServerPongData::build($this->server, $name, $this->networkId)));
	}

	/**
	 * Derives a deterministic 64-bit positive integer network ID from the identity key.
	 */
	private static function networkIdOf(string $identityPem) : int{
		$digest = hash("sha256", $identityPem, true);
		$id = unpack("P", substr($digest, 0, 8));

		return $id === false ? 1 : (($id[1] & PHP_INT_MAX) | 1);
	}

	public function shutdown() : void{
		$this->server->getTickSleeper()->removeNotifier($this->sleeperNotifierId);
		$this->thread->quit();
	}
}
