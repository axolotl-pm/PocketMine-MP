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

namespace pocketmine\block\tile;

use pocketmine\block\Shelf as BlockShelf;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\data\bedrock\item\SavedItemStackData;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\SimpleInventory;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\world\World;

class Shelf extends Spawnable implements Container{
	use ContainerTrait;

	private const SLOT_COUNT = 3;

	private SimpleInventory $inventory;

	public function __construct(World $world, Vector3 $pos){
		parent::__construct($world, $pos);
		$this->inventory = new SimpleInventory(self::SLOT_COUNT);
		$this->inventory->getListeners()->add(CallbackInventoryListener::onAnyChange(
			static function(Inventory $_) use ($world, $pos) : void{
				$block = $world->getBlock($pos);
				if($block instanceof BlockShelf){
					$world->setBlock($pos, $block);
				}
			}
		));
	}

	public function getInventory() : SimpleInventory{
		return $this->inventory;
	}

	public function getRealInventory() : SimpleInventory{
		return $this->inventory;
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->loadItems($nbt);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$this->saveItems($nbt);
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$this->writeItems($nbt, true);
	}

	protected function loadItems(CompoundTag $tag) : void{
		try{
			$inventoryTag = $tag->getListTag(Container::TAG_ITEMS, CompoundTag::class);
		}catch(UnexpectedTagTypeException){
			$inventoryTag = null;
		}
		if($inventoryTag !== null){
			$inventory = $this->getRealInventory();
			$listeners = $inventory->getListeners()->toArray();
			$inventory->getListeners()->remove(...$listeners);

			$newContents = [];
			$errorLogContext = "Shelf ($this->position)";
			foreach($inventoryTag as $slot => $itemNBT){
				$count = $itemNBT->getByte(SavedItemStackData::TAG_COUNT);
				if($count === 0){
					continue;
				}
				$newContents[$slot] = Item::safeNbtDeserialize($itemNBT, "$errorLogContext slot $slot");
			}
			$inventory->setContents($newContents);

			$inventory->getListeners()->add(...$listeners);
		}

		if(($lockTag = $tag->getTag(Container::TAG_LOCK)) instanceof StringTag){
			$this->lock = $lockTag->getValue();
		}
	}

	protected function saveItems(CompoundTag $tag) : void{
		$this->writeItems($tag, false);
	}

	private function writeItems(CompoundTag $tag, bool $network) : void{
		$items = [];
		foreach($this->getRealInventory()->getContents(true) as $slot => $item){
			if($item->isNull()){
				$items[$slot] = CompoundTag::create()
					->setByte(SavedItemStackData::TAG_COUNT, 0)
					->setShort(SavedItemData::TAG_DAMAGE, 0)
					->setString(SavedItemData::TAG_NAME, "")
					->setByte(SavedItemStackData::TAG_WAS_PICKED_UP, 0);
			}else{
				$items[$slot] = $network ?
					TypeConverter::getInstance()->getItemTranslator()->toNetworkNbt($item) :
					$item->nbtSerialize();
			}
		}

		$tag->setTag(Container::TAG_ITEMS, new ListTag($items, NBT::TAG_Compound));

		if(!$network && $this->lock !== null){
			$tag->setString(Container::TAG_LOCK, $this->lock);
		}
	}
}
