<?php

declare(strict_types=1);

namespace pocketmine\world;

use pocketmine\utils\SingletonTrait;
use function array_values;

final class DimensionRegistry{
	use SingletonTrait;

	/**
	 * @var Dimension[]
	 * @phpstan-var array<string, Dimension>
	 */
	private array $customDimensions = [];

	private bool $locked = false;

	public function register(Dimension $dimension) : void{
		if($this->locked){
			throw new \LogicException("Dimensions cannot be registered after the first player has joined");
		}
		if($dimension->isVanilla()){
			throw new \InvalidArgumentException("Vanilla dimensions are always registered");
		}
		$name = $dimension->getName();
		if(isset($this->customDimensions[$name])){
			throw new \InvalidArgumentException("Dimension $name is already registered");
		}
		$this->customDimensions[$name] = $dimension;
	}

	public function get(string $name) : ?Dimension{
		static $vanillaCache = null;
		if($vanillaCache === null){
			foreach(DefaultDimensions::getAll() as $dimension){
				$vanillaCache[$dimension->getName()] = $dimension;
			}
		}
		return $vanillaCache[$name] ?? $this->customDimensions[$name] ?? null;
	}

	public function isRegistered(Dimension $dimension) : bool{
		return $dimension->isVanilla() || ($this->customDimensions[$dimension->getName()] ?? null) === $dimension;
	}

	/**
	 * @return Dimension[]
	 * @phpstan-return list<Dimension>
	 */
	public function getCustomDimensions() : array{
		return array_values($this->customDimensions);
	}

	/**
	 * @internal
	 */
	public function lock() : void{
		$this->locked = true;
	}
}
