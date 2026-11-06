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

namespace pocketmine\form;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\handler\InGamePacketHandler;
use pocketmine\network\mcpe\InventoryManager;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\network\PacketHandlingException;
use pocketmine\player\Player;
use function str_repeat;

final class FormLifecycleTest extends TestCase{

	private function playerWithForm(Form $form) : Player{
		return FormTestPlayer::create(self::createStub(\Logger::class), $form);
	}

	private function packetHandler(Player $player) : InGamePacketHandler{
		return new InGamePacketHandler($player, self::createStub(NetworkSession::class), self::createStub(InventoryManager::class));
	}

	public function testResponseIsConsumedBeforeCallback() : void{
		$calls = 0;
		$form = new SimpleForm("Menu", "", [new FormButton("One")], static function(Player $player, ?int $selection) use (&$calls) : void{
			++$calls;
			self::assertFalse($player->hasPendingForm(7));
			self::assertFalse($player->onFormSubmit(7, $selection));
		});
		$player = $this->playerWithForm($form);
		self::assertTrue($player->onFormSubmit(7, 0));
		self::assertFalse($player->onFormSubmit(7, 0));
		self::assertSame(1, $calls);
	}

	public function testInvalidResponseIsConsumedWithoutCallback() : void{
		$form = new SimpleForm("Menu", "", [new FormButton("One")], static function(Player $player, ?int $selection) : void{
			self::fail("Invalid data reached the callback");
		});
		$player = $this->playerWithForm($form);
		self::assertTrue($player->onFormSubmit(7, -1));
		self::assertFalse($player->hasPendingForm(7));
		self::assertFalse($player->onFormSubmit(7, 0));
	}

	public function testCallbackExceptionStillConsumesResponse() : void{
		$form = new SimpleForm("Menu", "", [new FormButton("One")], static function(Player $player, ?int $selection) : void{
			throw new \LogicException("Plugin failure");
		});
		$player = $this->playerWithForm($form);
		try{
			$player->onFormSubmit(7, 0);
			self::fail("Plugin exceptions should propagate");
		}catch(\LogicException $e){
			self::assertSame("Plugin failure", $e->getMessage());
		}
		self::assertFalse($player->hasPendingForm(7));
	}

	/** @return iterable<string, array{ModalFormResponsePacket}> */
	public static function cancellationPackets() : iterable{
		yield "closed" => [ModalFormResponsePacket::cancel(7, ModalFormResponsePacket::CANCEL_REASON_CLOSED)];
		yield "busy" => [ModalFormResponsePacket::cancel(7, ModalFormResponsePacket::CANCEL_REASON_USER_BUSY)];
		yield "legacy null" => [ModalFormResponsePacket::response(7, "null\n")];
	}

	#[DataProvider("cancellationPackets")]
	public function testCancellation(ModalFormResponsePacket $packet) : void{
		$calls = 0;
		$player = $this->playerWithForm(new ModalForm("Confirm", "", "Yes", "No", static function(Player $player, ?bool $selection) use (&$calls) : void{
			self::assertNull($selection);
			++$calls;
		}));
		self::assertTrue($this->packetHandler($player)->handleModalFormResponse($packet));
		self::assertSame(1, $calls);
		self::assertFalse($player->hasPendingForm(7));
	}

	public function testCustomResponseFromPacket() : void{
		$calls = 0;
		$player = $this->playerWithForm(new CustomForm("Settings", [FormElement::toggle("Enabled")], static function(Player $player, ?array $values) use (&$calls) : void{
			self::assertSame([true], $values);
			++$calls;
		}));
		self::assertTrue($this->packetHandler($player)->handleModalFormResponse(ModalFormResponsePacket::response(7, "[true]\n")));
		self::assertSame(1, $calls);
	}

	public function testJsonObjectCannotMasqueradeAsResponseList() : void{
		$player = $this->playerWithForm(new CustomForm("Settings", [FormElement::toggle("Enabled")], static function(Player $player, ?array $values) : void{
			self::fail("JSON object reached the callback");
		}));
		self::assertTrue($this->packetHandler($player)->handleModalFormResponse(ModalFormResponsePacket::response(7, '{"0":true}')));
		self::assertFalse($player->hasPendingForm(7));
	}

	/** @return iterable<string, array{string}> */
	public static function malformedJson() : iterable{
		yield "invalid JSON" => ["["];
		yield "nested data" => ["[[true]]"];
		yield "oversized data" => [str_repeat(" ", 10 * 1024) . "null"];
	}

	#[DataProvider("malformedJson")]
	public function testPacketBounds(string $json) : void{
		$player = $this->playerWithForm(new ModalForm("Confirm", "", "Yes", "No", static function(Player $player, ?bool $selection) : void{
			self::fail("Malformed JSON reached the callback");
		}));
		$this->expectException(PacketHandlingException::class);
		$this->packetHandler($player)->handleModalFormResponse(ModalFormResponsePacket::response(7, $json));
	}
}
