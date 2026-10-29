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

namespace pocketmine\block\utils;

use pocketmine\data\runtime\RuntimeDataDescriber;
use function spl_object_id;

trait HorizontalConnectableTrait{
	/** @var HorizontalFacingOption[] spl_object_id(HorizontalFacingOption) => HorizontalFacingOption */
	protected array $connections = [];

	/**
	 * @see Block::describeBlockOnlyState()
	 */
	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->enumSet($this->connections, HorizontalFacingOption::cases());
	}

	public function isConnectedAt(HorizontalFacingOption $facing) : bool{
		return isset($this->connections[spl_object_id($facing)]);
	}

	public function setConnectedAt(HorizontalFacingOption $facing, bool $connected) : void{
		if($connected){
			$this->connections[spl_object_id($facing)] = $facing;
		}else{
			unset($this->connections[spl_object_id($facing)]);
		}
	}

	/**
	 * @see Block::onNearbyBlockChange()
	 */
	public function onNearbyBlockChange() : void{
		if($this->recalculateConnections()){
			$this->position->getWorld()->setBlock($this->position, $this);
		}
	}

	/**
	 * Implement this to (re)compute connections from the block's neighbours. Must return whether anything changed.
	 */
	abstract protected function recalculateConnections() : bool;
}
