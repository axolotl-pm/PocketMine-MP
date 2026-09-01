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

use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use function chr;
use function strlen;

final class NetherNetIpc{

	//thread to main
	public const T2M_SESSION_OPEN = 0;
	public const T2M_PACKET = 1;
	public const T2M_SESSION_CLOSE = 2;
	public const T2M_RECEIPT = 3;

	//main to thread
	public const M2T_SEND = 0;
	public const M2T_CLOSE_SESSION = 1;
	public const M2T_SET_SERVER_DATA = 2;

	private function __construct(){
		//NOOP
	}

	public static function setServerData(string $pongData) : string{
		$out = new ByteBufferWriter();
		$out->writeByteArray(chr(self::M2T_SET_SERVER_DATA));
		$out->writeByteArray($pongData);

		return $out->getData();
	}

	public static function sessionOpen(int $sessionId, string $address, int $port, string $publicKeyDigest) : string{
		$out = new ByteBufferWriter();
		$out->writeByteArray(chr(self::T2M_SESSION_OPEN));
		VarInt::writeUnsignedInt($out, $sessionId);
		VarInt::writeUnsignedInt($out, strlen($address));
		$out->writeByteArray($address);
		VarInt::writeUnsignedInt($out, $port);
		$out->writeByteArray($publicKeyDigest);

		return $out->getData();
	}

	public static function packet(int $sessionId, string $payload) : string{
		$out = new ByteBufferWriter();
		$out->writeByteArray(chr(self::T2M_PACKET));
		VarInt::writeUnsignedInt($out, $sessionId);
		$out->writeByteArray($payload);

		return $out->getData();
	}

	public static function sessionClose(int $sessionId, string $reason) : string{
		$out = new ByteBufferWriter();
		$out->writeByteArray(chr(self::T2M_SESSION_CLOSE));
		VarInt::writeUnsignedInt($out, $sessionId);
		$out->writeByteArray($reason);

		return $out->getData();
	}

	public static function receipt(int $sessionId, int $receiptId) : string{
		$out = new ByteBufferWriter();
		$out->writeByteArray(chr(self::T2M_RECEIPT));
		VarInt::writeUnsignedInt($out, $sessionId);
		VarInt::writeUnsignedInt($out, $receiptId);

		return $out->getData();
	}

	public static function send(int $sessionId, string $payload, ?int $receiptId) : string{
		$out = new ByteBufferWriter();
		$out->writeByteArray(chr(self::M2T_SEND));
		VarInt::writeUnsignedInt($out, $sessionId);
		// 0 represents no receipt requested; valid receipt IDs are incremented by 1
		VarInt::writeUnsignedInt($out, $receiptId === null ? 0 : $receiptId + 1);
		$out->writeByteArray($payload);

		return $out->getData();
	}

	public static function closeSession(int $sessionId) : string{
		$out = new ByteBufferWriter();
		$out->writeByteArray(chr(self::M2T_CLOSE_SESSION));
		VarInt::writeUnsignedInt($out, $sessionId);

		return $out->getData();
	}
}
