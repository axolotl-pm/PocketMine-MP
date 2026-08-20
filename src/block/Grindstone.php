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

use pocketmine\block\inventory\GrindstoneInventory;
use pocketmine\block\utils\GrindstoneAttachmentType;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

final class Grindstone extends Transparent implements HorizontalFacing{
	use HorizontalFacingTrait;

	private GrindstoneAttachmentType $attachmentType = GrindstoneAttachmentType::FLOOR;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->enum($this->attachmentType);
		$w->horizontalFacing($this->facing);
	}

	protected function recalculateCollisionBoxes() : array{
		$longAxis = match($this->attachmentType){
			GrindstoneAttachmentType::ONE_WALL => Facing::axis($this->facing),
			GrindstoneAttachmentType::CEILING, GrindstoneAttachmentType::FLOOR, GrindstoneAttachmentType::MULTIPLE => Facing::axis(Facing::UP),
		};
		$inset = (1 - 3 / 4) / 2;

		return [AxisAlignedBB::one()->contract($inset, $inset, $inset)->stretch($longAxis, $inset)];
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	public function getAttachmentType() : GrindstoneAttachmentType{ return $this->attachmentType; }

	/** @return $this */
	public function setAttachmentType(GrindstoneAttachmentType $attachmentType) : self{
		$this->attachmentType = $attachmentType;
		return $this;
	}

	private function canBeSupportedAt(Block $block, int $face) : bool{
		$support = $block->getSide($face);
		return $support->getTypeId() !== BlockTypeIds::AIR; //this block can be supported by anything, even liquids... #BLAMEMOJANG https://bugs.mojang.com/browse/MCPE-164666
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if(!$this->canBeSupportedAt($blockReplace, Facing::opposite($face))){
			return false;
		}
		if($face === Facing::UP || $face === Facing::DOWN){
			if($player !== null){
				$this->setFacing(Facing::opposite($player->getHorizontalFacing()));
			}
			$this->setAttachmentType($face === Facing::UP ? GrindstoneAttachmentType::FLOOR : GrindstoneAttachmentType::CEILING);
		}else{
			$this->setFacing($face);
			$this->setAttachmentType(GrindstoneAttachmentType::ONE_WALL);
		}
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onNearbyBlockChange() : void{
		$supportBlockDirection = match($this->attachmentType){
			GrindstoneAttachmentType::CEILING => Facing::UP,
			GrindstoneAttachmentType::FLOOR, GrindstoneAttachmentType::MULTIPLE => Facing::DOWN, //Grindstones have a multiple attachment type, but it has no unique functionality and just represents floor attachment.
			GrindstoneAttachmentType::ONE_WALL => Facing::opposite($this->facing),
		};

		if(!$this->canBeSupportedAt($this, $supportBlockDirection)){
			$world = $this->position->getWorld();
			if($world->useBreakOn($this->position, createParticles: true)){
				foreach($this->getDropsForCompatibleTool(VanillaItems::AIR()) as $drop){
					$world->dropItem($this->position->add(0.5, 0.5, 0.5), $drop);
				}
			}
		}
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($player !== null){
			$player->setCurrentWindow(new GrindstoneInventory($this->position));
		}
		return true;
	}
}
