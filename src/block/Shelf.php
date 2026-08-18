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

namespace pocketmine\block;

use pocketmine\block\tile\Shelf as TileShelf;
use pocketmine\block\utils\FacesOppositePlacingPlayerTrait;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\block\utils\WoodMaterial;
use pocketmine\block\utils\WoodTypeTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\ShelfActivateSound;
use pocketmine\world\sound\ShelfDeactivateSound;
use pocketmine\world\sound\ShelfMultiSwapSound;
use pocketmine\world\sound\ShelfPlaceItemSound;
use pocketmine\world\sound\ShelfSingleSwapSound;
use function array_slice;
use function array_unshift;
use function count;

class Shelf extends Transparent implements HorizontalFacing, PoweredByRedstone, WoodMaterial{
	use HorizontalFacingTrait;
	use FacesOppositePlacingPlayerTrait;
	use PoweredByRedstoneTrait;
	use WoodTypeTrait;

	private const SLOTS_PER_SHELF = 3;
	private const MAX_CONNECTED_SHELVES = 3;

	private const POWERED_SHELF_TYPE_UNCONNECTED = 0;
	private const POWERED_SHELF_TYPE_RIGHT = 1;
	private const POWERED_SHELF_TYPE_CENTER = 2;
	private const POWERED_SHELF_TYPE_LEFT = 3;

	private int $poweredShelfType = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacing($this->facing);
		$w->bool($this->powered);
		$w->boundedIntAuto(0, 3, $this->poweredShelfType);
	}

	public function getPoweredShelfType() : int{
		return $this->poweredShelfType;
	}

	/** @return $this */
	public function setPowered(bool $powered) : self{
		if($powered === $this->powered){
			return $this;
		}

		$this->powered = $powered;
		if($this->position->isValid()){
			$this->position->getWorld()->addSound($this->position, $powered ? new ShelfActivateSound() : new ShelfDeactivateSound());
		}

		return $this;
	}

	/** @return $this */
	public function setPoweredShelfType(int $poweredShelfType) : self{
		if($poweredShelfType < self::POWERED_SHELF_TYPE_UNCONNECTED || $poweredShelfType > self::POWERED_SHELF_TYPE_LEFT){
			throw new \InvalidArgumentException("Powered shelf type must be in range 0-3");
		}
		$this->poweredShelfType = $poweredShelfType;
		return $this;
	}

	public function onPostPlace() : void{
		$this->updatePoweredShelfType();
	}

	public function onNearbyBlockChange() : void{
		$this->updatePoweredShelfType();
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if(
			$face !== $this->facing ||
			$player === null ||
			$player->isSneaking() ||
			$clickVector->y < 0.25 ||
			$clickVector->y > 0.75
		){
			return false;
		}

		$tile = $this->position->getWorld()->getTile($this->position);
		if(!$tile instanceof TileShelf){
			return false;
		}

		$this->updatePoweredShelfType();
		if($this->powered){
			if(!$this->swapAllStacks($player)){
				return false;
			}
			$this->position->getWorld()->addSound($this->position, new ShelfMultiSwapSound());
		}else{
			$x = Facing::axis($face) === Axis::X ? $clickVector->z : $clickVector->x;
			$x = Facing::isPositive(Facing::rotateY($face, true)) ? 1 - $x : $x;
			$slot = match(true){
				$x < 1 / 3 => 0,
				$x < 2 / 3 => 1,
				default => 2
			};

			$inventory = $tile->getInventory();
			$shelfItem = $inventory->getItem($slot);
			if($shelfItem->isNull() && $item->isNull()){
				return true;
			}
			$inventory->setItem($slot, $item);
			$player->getInventory()->setItemInHand($shelfItem);
			$this->position->getWorld()->addSound(
				$this->position,
				$shelfItem->isNull() ? new ShelfPlaceItemSound() : new ShelfSingleSwapSound()
			);
		}

		return true;
	}

	public function getFuelTime() : int{
		return $this->woodType->isFlammable() ? 300 : 0;
	}

	public function getFlameEncouragement() : int{
		return $this->woodType->isFlammable() ? 30 : 0;
	}

	public function getFlammability() : int{
		return $this->woodType->isFlammable() ? 20 : 0;
	}

	/** @return array<int, Shelf> */
	private function getConnectedShelves() : array{
		if(!$this->powered){
			return [$this];
		}

		$left = [];
		$side = Facing::rotateY($this->facing, true);
		for($i = 0; $i < self::MAX_CONNECTED_SHELVES - 1; ++$i){
			$block = $this->getSide($side, $i + 1);
			if(!$block instanceof self || !$block->isPowered() || $block->getFacing() !== $this->facing){
				break;
			}
			array_unshift($left, $block);
		}

		$right = [];
		$side = Facing::rotateY($this->facing, false);
		for($i = 0; $i < self::MAX_CONNECTED_SHELVES - 1; ++$i){
			$block = $this->getSide($side, $i + 1);
			if(!$block instanceof self || !$block->isPowered() || $block->getFacing() !== $this->facing){
				break;
			}
			$right[] = $block;
		}

		return array_slice([...$left, $this, ...$right], 0, self::MAX_CONNECTED_SHELVES);
	}

	private function swapAllStacks(Player $player) : bool{
		$shelves = $this->getConnectedShelves();
		$tiles = [];
		foreach($shelves as $shelf){
			$tile = $shelf->getPosition()->getWorld()->getTile($shelf->getPosition());
			if(!$tile instanceof TileShelf){
				return false;
			}
			$tiles[] = $tile;
		}

		$inventory = $player->getInventory();
		$hotbarSlot = $inventory->getHotbarSize() - (count($tiles) * self::SLOTS_PER_SHELF);
		foreach($tiles as $tile){
			for($shelfSlot = 0; $shelfSlot < self::SLOTS_PER_SHELF; ++$shelfSlot){
				$shelfItem = $tile->getInventory()->getItem($shelfSlot);
				$hotbarItem = $inventory->getHotbarSlotItem($hotbarSlot++);
				$tile->getInventory()->setItem($shelfSlot, $hotbarItem);
				$inventory->setItem($hotbarSlot - 1, $shelfItem);
			}
		}

		return true;
	}

	private function updatePoweredShelfType() : void{
		$shelves = $this->getConnectedShelves();
		$count = count($shelves);
		$world = $this->position->getWorld();
		foreach($shelves as $index => $shelf){
			$type = match($count){
				1 => self::POWERED_SHELF_TYPE_UNCONNECTED,
				2 => $index === 0 ? self::POWERED_SHELF_TYPE_LEFT : self::POWERED_SHELF_TYPE_RIGHT,
				default => match($index){
					0 => self::POWERED_SHELF_TYPE_LEFT,
					$count - 1 => self::POWERED_SHELF_TYPE_RIGHT,
					default => self::POWERED_SHELF_TYPE_CENTER
				}
			};
			if($shelf->poweredShelfType !== $type){
				$shelf->poweredShelfType = $type;
				$world->setBlock($shelf->getPosition(), $shelf, false);
			}
		}
	}
}
