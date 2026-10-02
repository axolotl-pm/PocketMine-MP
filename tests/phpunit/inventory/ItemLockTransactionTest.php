<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\_\___|\__|_|  |_|_|_| |_|\_|\_\_|     |_|  |_|_|
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

namespace pocketmine\inventory;

use PHPUnit\Framework\TestCase;
use pocketmine\inventory\transaction\InventoryTransaction;
use pocketmine\inventory\transaction\TransactionValidationException;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\ItemLockMode;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;

class ItemLockTransactionTest extends TestCase{

	private function createPlayer() : Player{
		return new class extends Player{
			public function __construct(){ }

			public function __destruct(){ }
		};
	}

	public function testLockedInSlotCannotBeMoved() : void{
		$inventory = new SimpleInventory(1);
		$lockedItem = VanillaItems::APPLE();
		$lockedItem->setLockMode(ItemLockMode::LOCK_IN_SLOT);
		$inventory->setItem(0, $lockedItem);

		$transaction = new InventoryTransaction($this->createPlayer(), [
			new SlotChangeAction($inventory, 0, $lockedItem, VanillaItems::AIR())
		]);

		$this->expectException(TransactionValidationException::class);
		$transaction->validate();
	}

	public function testLockedInInventoryCannotCrossInventories() : void{
		$leftInventory = new SimpleInventory(1);
		$rightInventory = new SimpleInventory(1);
		$lockedItem = VanillaItems::APPLE();
		$lockedItem->setLockMode(ItemLockMode::LOCK_IN_INVENTORY);
		$leftInventory->setItem(0, $lockedItem);

		$transaction = new InventoryTransaction($this->createPlayer(), [
			new SlotChangeAction($leftInventory, 0, $lockedItem, VanillaItems::AIR()),
			new SlotChangeAction($rightInventory, 0, VanillaItems::AIR(), $lockedItem)
		]);

		$this->expectException(TransactionValidationException::class);
		$transaction->validate();
	}

	public function testLockedInInventoryCanMoveInsideTheSameInventory() : void{
		$inventory = new SimpleInventory(2);
		$lockedItem = VanillaItems::APPLE();
		$lockedItem->setLockMode(ItemLockMode::LOCK_IN_INVENTORY);
		$inventory->setItem(0, $lockedItem);

		$transaction = new InventoryTransaction($this->createPlayer(), [
			new SlotChangeAction($inventory, 0, $lockedItem, VanillaItems::AIR()),
			new SlotChangeAction($inventory, 1, VanillaItems::AIR(), $lockedItem)
		]);

		$transaction->validate();
		self::assertTrue(true);
	}
}