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

/**
 * Implemented by blocks whose persisted state is derived from their neighbours, and which have to be recalculated
 * when the stored state was written by a version that did not derive it.
 */
interface NeighbourDerivedState{

	/**
	 * Returns whether neighbouring blocks read state which this block derives, such as a stair's shape deciding which
	 * of its faces support a connection. Such blocks are recalculated before the ones reading them.
	 *
	 * Implementations must not read derived state from each other in both directions, since each block is recalculated
	 * exactly once.
	 */
	public function resolvesBeforeNeighbours() : bool;

	/**
	 * Recalculates the persisted state from the neighbouring blocks.
	 *
	 * @return bool true if the block state changed
	 */
	public function recalculateNeighbourState() : bool;
}
