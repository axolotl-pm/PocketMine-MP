<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\convert;

use pocketmine\network\mcpe\protocol\types\DimensionData;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\world\DefaultDimensions;
use pocketmine\world\Dimension;
use pocketmine\world\DimensionRegistry;
use Ramsey\Uuid\Uuid;
use function array_search;

/**
 * Maps dimensions to the IDs the client knows them by. Custom dimension IDs follow registration order and are not
 * related to the IDs used in world saves.
 *
 * Custom dimensions are only visible on the thread that registered them, so resolve IDs on the main thread.
 */
final class DimensionTranslator{
	/** The client ignores custom dimension definitions with a lower ID. */
	private const FIRST_CUSTOM_ID = 1000;
	private const GENERATOR_VOID = 5;

	public function __construct(
		private DimensionRegistry $registry
	){}

	public function toNetworkId(Dimension $dimension) : int{
		return match($dimension){
			DefaultDimensions::OVERWORLD() => DimensionIds::OVERWORLD,
			DefaultDimensions::NETHER() => DimensionIds::NETHER,
			DefaultDimensions::THE_END() => DimensionIds::THE_END,
			default => self::FIRST_CUSTOM_ID + $this->getCustomIndex($dimension)
		};
	}

	public function fromNetworkId(int $networkId) : ?Dimension{
		return match($networkId){
			DimensionIds::OVERWORLD => DefaultDimensions::OVERWORLD(),
			DimensionIds::NETHER => DefaultDimensions::NETHER(),
			DimensionIds::THE_END => DefaultDimensions::THE_END(),
			default => $this->registry->getCustomDimensions()[$networkId - self::FIRST_CUSTOM_ID] ?? null
		};
	}

	/**
	 * @return DimensionData[]
	 * @phpstan-return array<string, DimensionData>
	 */
	public function getCustomDimensionData() : array{
		$result = [];
		foreach($this->registry->getCustomDimensions() as $index => $dimension){
			$result[$dimension->getName()] = new DimensionData(
				$dimension->getMinY(),
				$dimension->getMaxY() - $dimension->getMinY(),
				self::GENERATOR_VOID,
				self::FIRST_CUSTOM_ID + $index,
				Uuid::fromString(Uuid::NIL),
				$dimension->getDefaultBiome()->getName(),
				$dimension->getCloudHeight(),
				$dimension->shouldRenderClouds()
			);
		}
		return $result;
	}

	private function getCustomIndex(Dimension $dimension) : int{
		$index = array_search($dimension, $this->registry->getCustomDimensions(), true);
		if($index === false){
			throw new \InvalidArgumentException("Dimension " . $dimension->getName() . " is not registered");
		}
		return $index;
	}
}
