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

namespace pocketmine\inventory\transaction;

use pocketmine\block\inventory\GrindstoneInventory;
use pocketmine\block\utils\GrindstoneHelper;
use pocketmine\crafting\GrindstoneCraftResult;
use pocketmine\inventory\transaction\action\DropItemAction;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\player\Player;
use pocketmine\world\sound\GrindstoneUseSound;
use function count;

final class GrindstoneTransaction extends InventoryTransaction{
	public function __construct(
		Player $source,
		private readonly GrindstoneInventory $inventory,
		private readonly GrindstoneCraftResult $result
	){
		parent::__construct($source);
	}

	public function validate() : void{
		$this->squashDuplicateSlotChanges();
		if(count($this->actions) === 0){
			throw new TransactionValidationException("Grindstone transaction must have at least one action to be executable");
		}

		$inputActions = [];
		foreach($this->actions as $action){
			$action->validate($this->source);
			if($action instanceof SlotChangeAction && $action->getInventory() === $this->inventory && $action->getSlot() < 2){
				$slot = $action->getSlot();
				if(isset($inputActions[$slot])){
					throw new TransactionValidationException("Grindstone input slot $slot was changed more than once");
				}
				$inputActions[$slot] = $action;
			}
		}

		for($slot = 0; $slot < 2; ++$slot){
			$input = $this->inventory->getItem($slot);
			$action = $inputActions[$slot] ?? null;
			if($input->isNull()){
				if($action !== null){
					throw new TransactionValidationException("Grindstone input slot $slot is empty but was changed");
				}
				continue;
			}
			if($action === null){
				throw new TransactionValidationException("Grindstone input slot $slot was not consumed");
			}
			if(!$action->getSourceItem()->equalsExact($input) || !$action->getTargetItem()->isNull()){
				throw new TransactionValidationException("Invalid change for grindstone input slot $slot");
			}
		}

		$expectedOutput = GrindstoneHelper::calculateOutput(
			$this->inventory->getItem(GrindstoneInventory::SLOT_INPUT),
			$this->inventory->getItem(GrindstoneInventory::SLOT_ADDITIONAL)
		);
		if($expectedOutput === null || !$expectedOutput->equalsExact($this->result->getOutput())){
			throw new TransactionValidationException("Invalid grindstone output");
		}

		$createdItems = 0;
		foreach($this->actions as $action){
			if($action instanceof SlotChangeAction && $action->getInventory() === $this->inventory && $action->getSlot() < 2){
				continue;
			}
			if($action instanceof DropItemAction){
				$target = $action->getTargetItem();
				if(!$target->canStackWith($expectedOutput)){
					throw new TransactionValidationException("Invalid item dropped from grindstone");
				}
				$createdItems += $target->getCount();
				continue;
			}
			if(!$action instanceof SlotChangeAction){
				throw new TransactionValidationException("Unexpected action in grindstone transaction");
			}

			$source = $action->getSourceItem();
			$target = $action->getTargetItem();
			if($target->isNull() || !$target->canStackWith($expectedOutput) || (!$source->isNull() && !$source->canStackWith($target))){
				throw new TransactionValidationException("Invalid item destination for grindstone output");
			}
			$added = $target->getCount() - ($source->isNull() ? 0 : $source->getCount());
			if($added < 1){
				throw new TransactionValidationException("Grindstone output was not added to a destination");
			}
			$createdItems += $added;
		}

		if($createdItems !== $this->result->getOutput()->getCount()){
			throw new TransactionValidationException("Expected " . $this->result->getOutput()->getCount() . " grindstone output items, got $createdItems");
		}
	}

	public function execute() : void{
		parent::execute();

		$holder = $this->inventory->getHolder();
		$world = $holder->getWorld();
		if(($experience = $this->result->getXpReward()) > 0){
			$world->dropExperience($holder->add(0.5, 0.5, 0.5), $experience);
		}
		$world->addSound($holder, new GrindstoneUseSound());
	}
}
