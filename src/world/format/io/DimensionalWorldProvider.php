<?php

declare(strict_types=1);

namespace pocketmine\world\format\io;

use pocketmine\world\Dimension;
use pocketmine\world\format\io\exception\DimensionHeightMismatchException;

/**
 * A world provider whose save can hold additional dimensions alongside the overworld.
 */
interface DimensionalWorldProvider extends WritableWorldProvider{

	/**
	 * Opens a provider for the given dimension inside this world's save. Its chunks are stored separately from the
	 * overworld's, and its world data is read from this provider without writing anything back.
	 *
	 * @throws DimensionHeightMismatchException if stored chunks of this dimension cover less height than it has now
	 */
	public function openDimension(Dimension $dimension, string $generatorName, string $generatorOptions, \Logger $logger) : WritableWorldProvider;
}
