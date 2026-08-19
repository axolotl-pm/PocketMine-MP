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
use pocketmine\block\utils\ShelfConnectionType;
use pocketmine\block\utils\ShelfSlot;
use pocketmine\block\utils\SupportType;
use pocketmine\block\utils\WoodMaterial;
use pocketmine\block\utils\WoodTypeTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\AxisAlignedBB;
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

	private const MAX_CONNECTED_SHELVES = 3;

	private ShelfConnectionType $poweredShelfType = ShelfConnectionType::UNCONNECTED;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacing($this->facing);
		$w->bool($this->powered);
		$w->enum($this->poweredShelfType);
	}

	protected function recalculateCollisionBoxes() : array{
		return [AxisAlignedBB::one()->trim($this->facing, 11 / 16)];
	}

	public function getSupportType(int $facing) : SupportType{
		return $facing === Facing::opposite($this->facing) ? SupportType::FULL : SupportType::NONE;
	}

	public function getPoweredShelfType() : ShelfConnectionType{
		return $this->poweredShelfType;
	}

	/** @return $this */
	public function setPoweredShelfType(ShelfConnectionType $poweredShelfType) : self{
		$this->poweredShelfType = $poweredShelfType;
		return $this;
	}

	/** @return $this */
	public function togglePowered(bool $powered) : self{
		if($powered === $this->powered){
			return $this;
		}

		$oldState = clone $this;
		$this->setPowered($powered);
		$this->position->getWorld()->addSound($this->position, $powered ? new ShelfActivateSound($oldState) : new ShelfDeactivateSound($oldState));

		return $this;
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
			$this->position->getWorld()->addSound($this->position, new ShelfMultiSwapSound($this));
		}else{
			$x = Facing::axis($face) === Axis::X ? $clickVector->z : $clickVector->x;
			$x = Facing::isPositive(Facing::rotateY($face, true)) ? 1 - $x : $x;
			$slot = ShelfSlot::fromBlockFaceCoordinate($x)->value;

			$inventory = $tile->getInventory();
			$shelfItem = $inventory->getItem($slot);
			if($shelfItem->isNull() && $item->isNull()){
				return true;
			}
			$inventory->setItem($slot, $item);
			$player->getInventory()->setItemInHand($shelfItem);
			$this->position->getWorld()->addSound($this->position, $shelfItem->isNull() ? new ShelfPlaceItemSound($this) : new ShelfSingleSwapSound($this));
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
		$hotbarSlot = $inventory->getHotbarSize() - (count($tiles) * count(ShelfSlot::cases()));
		foreach($tiles as $tile){
			for($shelfSlot = 0; $shelfSlot < count(ShelfSlot::cases()); ++$shelfSlot){
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
				1 => ShelfConnectionType::UNCONNECTED,
				2 => $index === 0 ? ShelfConnectionType::LEFT : ShelfConnectionType::RIGHT,
				default => match($index){
					0 => ShelfConnectionType::LEFT,
					$count - 1 => ShelfConnectionType::RIGHT,
					default => ShelfConnectionType::CENTER
				}
			};
			if($shelf->poweredShelfType !== $type){
				$shelf->poweredShelfType = $type;
				$world->setBlock($shelf->getPosition(), $shelf, false);
			}
		}
	}
}
