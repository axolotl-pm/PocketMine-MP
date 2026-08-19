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

		$leftSide = Facing::rotateY($this->facing, true);
		$rightSide = Facing::rotateY($this->facing, false);
		/** @return array<int, Shelf> */
		$getSideShelves = function(int $side) : array{
			$shelves = [];
			for($i = 1; $i <= self::MAX_CONNECTED_SHELVES; ++$i){
				$block = $this->getSide($side, $i);
				if(!$block instanceof self || !$block->isPowered() || $block->getFacing() !== $this->facing){
					break;
				}
				$shelves[] = $block;
			}
			return $shelves;
		};
		$left = $getSideShelves($leftSide);
		$right = $getSideShelves($rightSide);

		if($this->poweredShelfType !== ShelfConnectionType::UNCONNECTED){
			$leftCount = match($this->poweredShelfType){
				ShelfConnectionType::UNCONNECTED, ShelfConnectionType::LEFT => 0,
				ShelfConnectionType::CENTER => 1,
				ShelfConnectionType::RIGHT => isset($left[0]) && $left[0]->poweredShelfType === ShelfConnectionType::LEFT ? 1 : 2
			};
			$rightCount = match($this->poweredShelfType){
				ShelfConnectionType::UNCONNECTED, ShelfConnectionType::RIGHT => 0,
				ShelfConnectionType::CENTER => 1,
				ShelfConnectionType::LEFT => isset($right[0]) && $right[0]->poweredShelfType === ShelfConnectionType::RIGHT ? 1 : 2
			};
			$connected = [$this];
			foreach(array_slice($left, 0, $leftCount) as $block){
				if($block->poweredShelfType === ShelfConnectionType::UNCONNECTED || $block->poweredShelfType === ShelfConnectionType::RIGHT){
					break;
				}
				array_unshift($connected, $block);
			}
			foreach(array_slice($right, 0, $rightCount) as $block){
				if($block->poweredShelfType === ShelfConnectionType::UNCONNECTED || $block->poweredShelfType === ShelfConnectionType::LEFT){
					break;
				}
				$connected[] = $block;
			}
			return $connected;
		}

		$leftGroup = isset($left[0]) && $left[0]->poweredShelfType !== ShelfConnectionType::UNCONNECTED ? $left[0]->getConnectedShelves() : null;
		$rightGroup = isset($right[0]) && $right[0]->poweredShelfType !== ShelfConnectionType::UNCONNECTED ? $right[0]->getConnectedShelves() : null;
		if($leftGroup !== null && $rightGroup !== null && count($leftGroup) + count($rightGroup) < self::MAX_CONNECTED_SHELVES){
			return [...$leftGroup, $this, ...$rightGroup];
		}
		foreach([$leftGroup, $rightGroup] as $side => $group){
			if($group !== null && count($group) < self::MAX_CONNECTED_SHELVES){
				return $side === 0 ? [...$group, $this] : [$this, ...$group];
			}
		}
		if($leftGroup !== null || $rightGroup !== null){
			return [$this];
		}

		$unconnected = [$this];
		foreach($left as $block){
			if($block->poweredShelfType !== ShelfConnectionType::UNCONNECTED){
				break;
			}
			array_unshift($unconnected, $block);
		}
		if(count($unconnected) > self::MAX_CONNECTED_SHELVES){
			return [$this];
		}
		foreach($right as $block){
			if($block->poweredShelfType !== ShelfConnectionType::UNCONNECTED){
				break;
			}
			$unconnected[] = $block;
		}

		return array_slice($unconnected, 0, self::MAX_CONNECTED_SHELVES);
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
