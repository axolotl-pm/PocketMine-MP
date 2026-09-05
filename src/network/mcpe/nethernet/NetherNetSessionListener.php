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

use pocketmine\nethernet\ServerEventListener;
use pocketmine\nethernet\session\DisconnectReason;
use pocketmine\nethernet\session\Reliability;
use pocketmine\nethernet\session\Session;
use pocketmine\nethernet\session\SessionException;
use function count;
use function strlen;
use function strrpos;
use function substr;

final class NetherNetSessionListener implements ServerEventListener{

	/** @var array<int, Session> */
	private array $sessions = [];

	/**
	 * Total bytes queued per session.
	 *
	 * @var array<int, int>
	 */
	private array $queuedBytes = [];

	/**
	 * Pending ACK receipts mapped by session ID and tracking watermark.
	 *
	 * @var array<int, list<array{int, int}>>
	 */
	private array $pendingReceipts = [];

	public function __construct(
		private readonly NetherNetChannel $out
	){}

	public function onSessionOpen(Session $session) : void{
		$id = $session->getId();
		$this->sessions[$id] = $session;
		$this->queuedBytes[$id] = 0;
		$this->pendingReceipts[$id] = [];

		[$address, $port] = self::splitAddress($session->getRemoteAddress());
		$this->out->write(NetherNetIpc::sessionOpen(
			$id,
			$address,
			$port,
			$session->getIdentity()?->publicKey->getDigest() ?? ""
		));
	}

	public function onPacketReceive(Session $session, string $payload, Reliability $reliability) : void{
		$this->out->write(NetherNetIpc::packet($session->getId(), $payload));
	}

	public function onSessionClose(Session $session, DisconnectReason $reason) : void{
		$id = $session->getId();
		unset($this->sessions[$id], $this->queuedBytes[$id], $this->pendingReceipts[$id]);

		$this->out->write(NetherNetIpc::sessionClose($id, $reason->value));
	}

	public function send(int $sessionId, string $payload, ?int $receiptId) : void{
		$session = $this->sessions[$sessionId] ?? null;
		if($session === null){
			return;
		}

		try{
			$session->send($payload);
		}catch(SessionException $e){
			$session->close(DisconnectReason::SEND_FAILED);

			return;
		}

		$this->queuedBytes[$sessionId] += strlen($payload);
		if($receiptId !== null){
			$this->pendingReceipts[$sessionId][] = [$receiptId, $this->queuedBytes[$sessionId]];
		}
	}

	public function close(int $sessionId, DisconnectReason $reason = DisconnectReason::SERVER_DISCONNECT) : void{
		($this->sessions[$sessionId] ?? null)?->initiateDisconnect($reason);
	}

	/**
	 * Checks and dispatches ACK receipts for packets that have cleared the send buffer.
	 */
	public function flushReceipts() : void{
		foreach($this->pendingReceipts as $sessionId => $pending){
			if(count($pending) === 0){
				continue;
			}

			$session = $this->sessions[$sessionId] ?? null;
			if($session === null){
				continue;
			}

			$delivered = $this->queuedBytes[$sessionId] - $session->getBufferedAmount();
			$remaining = [];
			foreach($pending as [$receiptId, $watermark]){
				if($watermark <= $delivered){
					$this->out->write(NetherNetIpc::receipt($sessionId, $receiptId));
				}else{
					$remaining[] = [$receiptId, $watermark];
				}
			}

			$this->pendingReceipts[$sessionId] = $remaining;
		}
	}

	/**
	 * @return array{string, int}
	 */
	private static function splitAddress(?string $address) : array{
		if($address === null){
			return ["0.0.0.0", 0];
		}

		$separator = strrpos($address, ":");
		if($separator === false){
			return [$address, 0];
		}

		return [substr($address, 0, $separator), (int) substr($address, $separator + 1)];
	}
}
