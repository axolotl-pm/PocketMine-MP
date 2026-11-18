<?php

declare(strict_types=1);

namespace pocketmine\world\format\io\exception;

use pocketmine\world\format\ChunkException;

/**
 * Thrown when stored chunk data covers a smaller height range than the dimension it is loaded into. Callers must not
 * regenerate or overwrite such chunks, since the data is intact and only the dimension definition changed.
 */
final class DimensionHeightMismatchException extends ChunkException{

}
