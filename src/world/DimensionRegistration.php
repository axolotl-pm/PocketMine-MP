<?php

declare(strict_types=1);

namespace pocketmine\world;

final readonly class DimensionRegistration{

	public function __construct(
		public Dimension $dimension,
		public string $generatorName,
		public string $generatorOptions
	){}
}
