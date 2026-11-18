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

namespace pocketmine\event\world;

use pocketmine\world\DefaultDimensions;
use pocketmine\world\Dimension;
use pocketmine\world\DimensionRegistration;
use pocketmine\world\DimensionRegistry;
use pocketmine\world\format\io\DimensionalWorldProvider;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\generator\GeneratorManagerEntry;
use pocketmine\world\generator\InvalidGeneratorOptionsException;
use pocketmine\world\World;
use function array_map;
use function array_values;

/**
 * Called when a world is loaded or newly created/generated.
 *
 * When the loaded world is an overworld, plugins may register additional dimensions to be loaded from the same
 * save. The worlds of those dimensions are loaded right after this event, and each of them gets its own
 * WorldLoadEvent, in which no further dimensions can be registered.
 */
class WorldLoadEvent extends WorldEvent{

	/**
	 * @var DimensionRegistration[]
	 * @phpstan-var array<string, DimensionRegistration>
	 */
	private array $registrations = [];

	public function __construct(World $world, private bool $allowDimensionRegistration = false){
		parent::__construct($world);
	}

	public function allowsDimensionRegistration() : bool{
		return $this->allowDimensionRegistration;
	}

	/**
	 * Registers a dimension to be loaded from this world's save, generated with the given generator.
	 *
	 * @throws InvalidGeneratorOptionsException
	 */
	public function registerDimension(Dimension $dimension, GeneratorManagerEntry $generator, string $generatorOptions = "") : void{
		if(!$this->allowDimensionRegistration){
			throw new \LogicException("Cannot register dimensions in a non-overworld world");
		}
		if($dimension === DefaultDimensions::OVERWORLD() || !DimensionRegistry::getInstance()->isRegistered($dimension)){
			throw new \InvalidArgumentException("Dimension " . $dimension->getName() . " must be registered in " . DimensionRegistry::class . " first");
		}
		$name = $dimension->getName();
		if(isset($this->registrations[$name])){
			throw new \InvalidArgumentException("Dimension $name is already registered for this world");
		}
		$world = $this->getWorld();
		if(!($world->getProvider() instanceof DimensionalWorldProvider)){
			throw new \InvalidArgumentException("The format of world " . $world->getFolderName() . " cannot store additional dimensions");
		}
		$generator->validateGeneratorOptions($generatorOptions);

		$this->registrations[$name] = new DimensionRegistration(
			$dimension,
			GeneratorManager::getInstance()->getGeneratorName($generator->getGeneratorClass()),
			$generatorOptions
		);
	}

	/**
	 * @return Dimension[]
	 * @phpstan-return list<Dimension>
	 */
	public function getRegisteredDimensions() : array{
		return array_values(array_map(fn(DimensionRegistration $registration) => $registration->dimension, $this->registrations));
	}

	/**
	 * @return DimensionRegistration[]
	 * @phpstan-return list<DimensionRegistration>
	 * @internal
	 */
	public function getRegistrations() : array{
		return array_values($this->registrations);
	}
}
