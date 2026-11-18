<?php

declare(strict_types=1);

namespace pocketmine\world\format\io\data;

use pocketmine\math\Vector3;
use pocketmine\world\format\io\WorldData;

/**
 * World data of a dimension stored inside another world's save. Everything except the generator is read from the
 * owning world's data, and nothing is written back to it.
 */
final class SharedWorldData implements WorldData{

	private Vector3 $spawn;

	public function __construct(
		private WorldData $base,
		private string $generator,
		private string $generatorOptions
	){
		$this->spawn = $base->getSpawn();
	}

	public function save() : void{}

	public function getName() : string{ return $this->base->getName(); }

	public function setName(string $value) : void{}

	public function getGenerator() : string{ return $this->generator; }

	public function getGeneratorOptions() : string{ return $this->generatorOptions; }

	public function getSeed() : int{ return $this->base->getSeed(); }

	public function getTime() : int{ return $this->base->getTime(); }

	public function setTime(int $value) : void{}

	public function getSpawn() : Vector3{ return $this->spawn; }

	public function setSpawn(Vector3 $pos) : void{
		$this->spawn = $pos;
	}

	public function getDifficulty() : int{ return $this->base->getDifficulty(); }

	public function setDifficulty(int $difficulty) : void{}

	public function getRainTime() : int{ return $this->base->getRainTime(); }

	public function setRainTime(int $ticks) : void{}

	public function getRainLevel() : float{ return $this->base->getRainLevel(); }

	public function setRainLevel(float $level) : void{}

	public function getLightningTime() : int{ return $this->base->getLightningTime(); }

	public function setLightningTime(int $ticks) : void{}

	public function getLightningLevel() : float{ return $this->base->getLightningLevel(); }

	public function setLightningLevel(float $level) : void{}
}
