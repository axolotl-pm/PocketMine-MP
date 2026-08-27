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

namespace pocketmine\entity\riding;

use pocketmine\block\Block;
use pocketmine\block\Door;
use pocketmine\block\FenceGate;
use pocketmine\block\Trapdoor;
use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeFactory;
use pocketmine\entity\Entity;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use function abs;
use function array_search;
use function array_splice;
use function array_values;
use function cos;
use function count;
use function deg2rad;
use function floor;
use function in_array;
use function min;
use function sin;
use function sqrt;

/**
 * Manages passengers, seat links, and positioning for a {@link Mountable} entity.
 */
final class LinkManager{

	private const MAX_RIDING_DEPTH = 16;

	/**
	 * @var int[]
	 * @phpstan-var list<int>
	 */
	private array $passengers = [];

	/**
	 * @var mixed[]|null
	 * @phpstan-var array{int, float, float}|null driver entity id, strafe, forward
	 */
	private ?array $riderInput = null;

	private ?Vector3 $previousPosition = null;

	/**
	 * Position delta from the vehicle's last completed tick.
	 *
	 * Stored as a delta rather than an old position so dismounts processed after the vehicle
	 * has already ticked in the same world tick don't see identical positions and lose heading.
	 */
	private Vector3 $travel;

	/**
	 * @param Seat[] $seats
	 * @phpstan-param list<Seat> $seats
	 */
	public function __construct(
		private Entity $owner,
		private int $seatCount = 1,
		private array $seats = []
	){
		$this->travel = Vector3::zero();
		$this->syncVehicleProperties();
	}

	public function getSeatCount() : int{ return $this->seatCount; }

	/**
	 * @return Seat[]
	 * @phpstan-return list<Seat>
	 */
	public function getSeats() : array{ return $this->seats; }

	/**
	 * @param Seat[] $seats
	 * @phpstan-param list<Seat> $seats
	 */
	public function setSeats(int $seatCount, array $seats) : void{
		$this->seatCount = $seatCount;
		$this->seats = $seats;
	}

	/**
	 * @return int[]
	 * @phpstan-return list<int>
	 */
	public function getPassengerIds() : array{
		return $this->passengers;
	}

	/**
	 * @return Entity[]
	 * @phpstan-return list<Entity>
	 */
	public function getPassengers() : array{
		$result = [];
		foreach($this->passengers as $id){
			$entity = $this->owner->getWorld()->getEntity($id);
			if($entity !== null){
				$result[] = $entity;
			}
		}
		return $result;
	}

	public function isPassenger(Entity $entity) : bool{
		return in_array($entity->getId(), $this->passengers, true);
	}

	public function hasPassengers() : bool{
		return count($this->passengers) > 0;
	}

	/**
	 * Returns the entity controlling this vehicle, or null if unseated or uncontrolled.
	 */
	public function getControllingPassenger() : ?Entity{
		$owner = $this->owner;
		if(!$owner instanceof Controllable){
			return null;
		}
		$id = $this->passengers[$owner->getControllingSeatIndex()] ?? null;
		return $id !== null ? $owner->getWorld()->getEntity($id) : null;
	}

	/**
	 * Buffers the driver's steering input to be applied once on the next vehicle tick.
	 *
	 * @internal
	 */
	public function setRiderInput(Entity $rider, float $strafe, float $forward) : void{
		$this->riderInput = [$rider->getId(), $strafe, $forward];
	}

	/**
	 * Applies the driver's latest input once for this tick.
	 */
	private function tickRiderInput() : void{
		//Client input packets do not align 1:1 with server ticks (a tick may receive 0 or 2 packets).
		//Applying inputs immediately per-packet causes double acceleration against single friction,
		//doubling the vehicle's terminal speed. Buffering and consuming once per tick keeps speed consistent.
		$input = $this->riderInput;
		$owner = $this->owner;
		if($input === null || !$owner instanceof Controllable){
			return;
		}

		$rider = $this->getControllingPassenger();
		if($rider === null || $rider->getId() !== $input[0]){
			$this->riderInput = null;
			return;
		}
		$owner->onRiderInput($rider, $input[1], $input[2]);
	}

	public function isControlledBy(Entity $entity) : bool{
		return $this->getControllingPassenger() === $entity;
	}

	/**
	 * Synchronizes vehicle metadata and jump strength attributes with client expectations.
	 */
	public function syncVehicleProperties() : void{
		$owner = $this->owner;
		$properties = $owner->getNetworkProperties();
		$strength = $owner instanceof Controllable ? $owner->getJumpStrength() : 0.0;

		$properties->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, $owner instanceof Controllable);
		$properties->setGenericFlag(EntityMetadataFlags::CAN_POWER_JUMP, $strength > 0.0);
		$properties->setByte(
			EntityMetadataProperties::CONTROLLING_RIDER_SEAT_NUMBER,
			$owner instanceof Controllable ? $owner->getControllingSeatIndex() : 0
		);

		if($strength > 0.0){
			$attributes = $owner->getAttributeMap();
			$jumpStrength = $attributes->get(Attribute::HORSE_JUMP_STRENGTH);
			if($jumpStrength === null){
				$jumpStrength = AttributeFactory::getInstance()->mustGet(Attribute::HORSE_JUMP_STRENGTH);
				$attributes->add($jumpStrength);
			}
			$jumpStrength->setValue($strength, fit: true, forceSend: true);
		}
	}

	/**
	 * Adds a passenger to this vehicle.
	 */
	public function addPassenger(Entity $entity) : bool{
		if($entity->isClosed() || $this->owner->isClosed()){
			return false;
		}
		if($entity->getWorld() !== $this->owner->getWorld()){
			return false;
		}
		if($this->isPassenger($entity) || count($this->passengers) >= $this->seatCount){
			return false;
		}
		if($this->wouldCycle($entity)){
			return false;
		}

		$currentVehicle = $entity->getVehicle();
		$currentVehicle?->getLinkManager()->detach($entity, immediate: false, causedByRider: true, notify: false);

		$seatIndex = count($this->passengers);
		$this->passengers[] = $entity->getId();
		$entity->setRidingVehicle($this->owner, $seatIndex);

		$this->syncVehicleProperties();
		$this->broadcastLinks();
		$this->syncSeats();
		return true;
	}

	public function removePassenger(Entity $entity, bool $causedByRider = true, bool $immediate = false) : void{
		$this->detach($entity, $immediate, $causedByRider, notify: true);
	}

	public function removeAllPassengers(bool $causedByRider = false, bool $immediate = false) : void{
		foreach($this->getPassengers() as $passenger){
			$this->removePassenger($passenger, $causedByRider, $immediate);
		}
		$this->passengers = [];
	}

	/**
	 * Drives the vehicle from the driver's input, then validates and cleans up invalid passenger links.
	 */
	public function tick() : void{
		if(count($this->passengers) === 0){
			return;
		}
		$this->tickRiderInput();

		$position = $this->owner->getPosition()->asVector3();
		if($this->previousPosition !== null){
			$this->travel = $position->subtractVector($this->previousPosition);
		}
		$this->previousPosition = $position;

		$world = $this->owner->getWorld();
		$ownerId = $this->owner->getId();

		$missing = [];
		$evict = [];
		$seated = 0;
		foreach($this->passengers as $id){
			$passenger = $world->getEntity($id);
			if($passenger === null || $passenger->isClosed()){
				$missing[] = $id;
			}elseif($passenger->getVehicleId() !== $ownerId || $seated >= $this->seatCount){
				$evict[] = $passenger;
			}else{
				$seated++;
			}
		}
		if(count($missing) === 0 && count($evict) === 0){
			return;
		}

		foreach($missing as $id){
			$index = array_search($id, $this->passengers, true);
			if($index !== false){
				array_splice($this->passengers, $index, 1);
			}
		}
		$this->passengers = array_values($this->passengers);
		foreach($evict as $passenger){
			$this->detach($passenger, immediate: false, causedByRider: false, notify: true);
		}
		if(count($evict) === 0){
			$this->broadcastLinks();
			$this->syncSeats();
		}
	}

	/**
	 * @return EntityLink[]
	 * @phpstan-return list<EntityLink>
	 */
	public function buildLinks() : array{
		$links = [];
		foreach($this->passengers as $index => $id){
			$links[] = $this->makeLink($id, $this->linkTypeFor($index), immediate: false, causedByRider: true);
		}
		return $links;
	}

	/**
	 * Returns the world position of the given passenger, or null if not seated.
	 */
	public function getPassengerPosition(Entity $passenger) : ?Vector3{
		$offset = $this->getSeatOffset($passenger);
		if($offset === null){
			return null;
		}
		$location = $this->owner->getLocation();
		$angle = deg2rad(-$location->yaw);
		$cos = cos($angle);
		$sin = sin($angle);
		return new Vector3(
			$location->x + ($offset->x * $cos) + ($offset->z * $sin),
			$location->y + $offset->y,
			$location->z + ($offset->z * $cos) - ($offset->x * $sin)
		);
	}

	public function getSeatOffset(Entity $passenger) : ?Vector3{
		$index = array_search($passenger->getId(), $this->passengers, true);
		if($index === false){
			return null;
		}
		$seat = $this->findSeat($index);
		return $seat !== null ? $this->seatOffset($seat, $passenger) : null;
	}

	private function seatOffset(Seat $seat, Entity $passenger) : Vector3{
		$scaled = $seat->position->multiply($this->owner->getScale());
		return $scaled->withComponents(null, $scaled->y + $passenger->getRidingHeight(), null);
	}

	/**
	 * Synchronizes seat positions and rotation limits for all current passengers.
	 */
	public function syncSeats() : void{
		foreach($this->passengers as $index => $id){
			$passenger = $this->owner->getWorld()->getEntity($id);
			if($passenger === null){
				continue;
			}
			$seat = $this->findSeat($index);
			$properties = $passenger->getNetworkProperties();
			if($seat === null){
				$this->clearSeatProperties($properties);
				continue;
			}

			$lock = $seat->lockRiderRotation;
			$properties->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, $this->seatOffset($seat, $passenger));
			$properties->setByte(EntityMetadataProperties::RIDER_ROTATION_LOCKED, $lock !== null ? 1 : 0);
			$properties->setFloat(EntityMetadataProperties::RIDER_MAX_ROTATION, $lock ?? 0.0);
			$properties->setFloat(EntityMetadataProperties::RIDER_MIN_ROTATION, $lock !== null ? -$lock : 0.0);
			$properties->setFloat(EntityMetadataProperties::RIDER_SEAT_ROTATION_OFFSET, $seat->rotateRiderBy);
		}
	}

	private function findSeat(int $index) : ?Seat{
		$riderCount = count($this->passengers);
		$eligible = 0;
		foreach($this->seats as $seat){
			if($seat->isEligibleFor($riderCount, $this->seatCount)){
				if($eligible === $index){
					return $seat;
				}
				$eligible++;
			}
		}
		return null;
	}

	private function wouldCycle(Entity $entity) : bool{
		$world = $this->owner->getWorld();
		$current = $this->owner;
		for($depth = 0; $depth < self::MAX_RIDING_DEPTH; $depth++){
			if($current === $entity){
				return true;
			}
			$vehicleId = $current->getVehicleId();
			if($vehicleId === null){
				return false;
			}
			$vehicle = $world->getEntity($vehicleId);
			if($vehicle === null){
				return false;
			}
			$current = $vehicle;
		}
		return true;
	}

	private function detach(Entity $entity, bool $immediate, bool $causedByRider, bool $notify) : void{
		$index = array_search($entity->getId(), $this->passengers, true);
		if($index === false){
			return;
		}
		array_splice($this->passengers, $index, 1);
		$this->passengers = array_values($this->passengers);
		//Pass the original seat index before array_splice to notify dismount hooks correctly
		$entity->setRidingVehicle(null, $index);

		$this->clearSeatProperties($entity->getNetworkProperties());

		if($notify){
			$this->broadcast($this->makeLink($entity->getId(), EntityLink::TYPE_REMOVE, $immediate, $causedByRider));
		}
		$this->broadcastLinks();
		$this->syncSeats();
	}

	/**
	 * Finds a suitable dismount position next to the vehicle for the exiting rider, or null if obstructed.
	 *
	 * This tests 24 candidate offsets, nearest sides -> corners -> back -> front across level, +1, -1 vertical offsets. (ref. VehicleUtils::testPosFollowingEjectPattern in BDS)
	 */
	public function getDismountPosition(Entity $rider) : ?Vector3{
		$vehiclePos = $this->owner->getPosition();
		[$forward, $side] = self::vehicleDirections($this->travel);

		$baseX = floor($vehiclePos->x) + 0.5;
		$baseY = floor($vehiclePos->y);
		$baseZ = floor($vehiclePos->z) + 0.5;
		//Vertical search order: level with vehicle (0) -> above (+1) -> below (-1).
		foreach([0.0, 1.0, -1.0] as $up){
			foreach(self::exitOffsets($forward, $side) as $offset){
				$floorHeight = $this->blockFloorHeight(
					(int) floor($baseX + $offset->x),
					(int) ($baseY + $up),
					(int) floor($baseZ + $offset->z)
				);
				if($floorHeight === null){
					continue;
				}
				if(!$this->fitsAt(new Vector3($baseX + $offset->x, $baseY + $up + $floorHeight, $baseZ + $offset->z), $rider)){
					continue;
				}

				//Candidate offsets are tested from the vehicle's block center, but applied to the rider's actual position (BDS behavior).
				$riderPos = $rider->getPosition();
				return new Vector3(
					$riderPos->x + $offset->x,
					$riderPos->y + ($baseY - $rider->boundingBox->minY) + $up + $floorHeight,
					$riderPos->z + $offset->z
				);
			}
		}
		return null;
	}

	/**
	 * Returns the 8 horizontal exit offsets in test priority order: nearest sides, corners, back, then front.
	 *
	 * @return Vector3[]
	 * @phpstan-return list<Vector3>
	 */
	private static function exitOffsets(Vector3 $forward, Vector3 $side) : array{
		return [
			$side,
			$side->multiply(-1.0),
			$side->subtractVector($forward),
			$side->multiply(-1.0)->subtractVector($forward),
			$forward->addVector($side),
			$forward->subtractVector($side),
			$forward->multiply(-1.0),
			$forward
		];
	}

	/**
	 * Returns the vehicle's forward and sideways axes aligned to its last tick movement.
	 *
	 * @return Vector3[]
	 * @phpstan-return array{Vector3, Vector3}
	 */
	private static function vehicleDirections(Vector3 $travel) : array{
		//0.0099^2 (BDS threshold for considering a vehicle moving)
		if((($travel->x ** 2) + ($travel->z ** 2)) <= 9.801e-05){
			return [new Vector3(-1.0, 0.0, 0.0), new Vector3(0.0, 0.0, -1.0)];
		}
		//Ties go to z (BDS behavior)
		$x = abs($travel->x) > abs($travel->z) ? $travel->x : 0.0;
		$z = abs($travel->z) >= abs($travel->x) ? $travel->z : 0.0;
		$length = sqrt(($x ** 2) + ($z ** 2));
		return [
			new Vector3($x / $length, 0.0, $z / $length),
			new Vector3(-$z / $length, 0.0, $x / $length)
		];
	}

	/**
	 * Returns the standing floor height offset within the block, or null if the position cannot be stood upon.
	 */
	private function blockFloorHeight(int $x, int $y, int $z) : ?float{
		$world = $this->owner->getWorld();

		$block = $world->getBlockAt($x, $y, $z);
		$top = self::collisionTop($block);
		if($top !== null){
			$height = $top - $y;
			if($height < 1.0){
				return $height;
			}
			//A full block only counts as somewhere to stand if a rider could pass through it anyway.
			if(!self::passableForExit($block)){
				return null;
			}
		}

		//Nothing to stand on in this block, so stand on top of whatever is under it. Measured from this block's floor,
		//not the one below, so a full cube underneath comes out as exactly zero.
		$below = $world->getBlockAt($x, $y - 1, $z);
		$belowTop = self::collisionTop($below);
		if($belowTop === null){
			return null;
		}
		$height = $belowTop - $y;
		return $height >= 0.0 && !self::passableForExit($below) ? $height : null;
	}

	private static function collisionTop(Block $block) : ?float{
		$top = null;
		foreach($block->getCollisionBoxes() as $box){
			if($top === null || $box->maxY > $top){
				$top = $box->maxY;
			}
		}
		return $top;
	}

	/**
	 * Returns whether the rider fits standing at the candidate position.
	 * Width is clamped to max 1.0 on both horizontal axes, height is unscaled from feet. (BDS behavior)
	 */
	private function fitsAt(Vector3 $position, Entity $rider) : bool{
		$size = $rider->boundingBox;
		$halfWidth = min($size->getXLength(), 1.0) * 0.5;
		$box = new AxisAlignedBB(
			$position->x - $halfWidth, $position->y, $position->z - $halfWidth,
			$position->x + $halfWidth, $position->y + $size->getYLength(), $position->z + $halfWidth
		);

		$world = $this->owner->getWorld();
		for($x = (int) floor($box->minX); $x <= (int) floor($box->maxX); $x++){
			for($y = (int) floor($box->minY); $y <= (int) floor($box->maxY); $y++){
				for($z = (int) floor($box->minZ); $z <= (int) floor($box->maxZ); $z++){
					$block = $world->getBlockAt($x, $y, $z);
					if(self::passableForExit($block)){
						continue;
					}
					foreach($block->getCollisionBoxes() as $collision){
						if($box->intersectsWith($collision)){
							return false;
						}
					}
				}
			}
		}
		return true;
	}

	/**
	 * Returns whether a dismounting rider can safely occupy the given block.
	 */
	private static function passableForExit(Block $block) : bool{
		if($block->canClimb()){
			return true;
		}
		return ($block instanceof Door || $block instanceof FenceGate || $block instanceof Trapdoor) && $block->isOpen();
	}

	private function clearSeatProperties(EntityMetadataCollection $properties) : void{
		$properties->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, Vector3::zero());
		$properties->setByte(EntityMetadataProperties::RIDER_ROTATION_LOCKED, 0);
		$properties->setFloat(EntityMetadataProperties::RIDER_MAX_ROTATION, 0.0);
		$properties->setFloat(EntityMetadataProperties::RIDER_MIN_ROTATION, 0.0);
		$properties->setFloat(EntityMetadataProperties::RIDER_SEAT_ROTATION_OFFSET, 0.0);
	}

	private function linkTypeFor(int $index) : int{
		return $index === 0 ? EntityLink::TYPE_RIDER : EntityLink::TYPE_PASSENGER;
	}

	private function makeLink(int $passengerId, int $type, bool $immediate, bool $causedByRider) : EntityLink{
		return new EntityLink($this->owner->getId(), $passengerId, $type, $immediate, $causedByRider, 0.0);
	}

	private function broadcastLinks() : void{
		foreach($this->passengers as $index => $id){
			$this->broadcast($this->makeLink($id, $this->linkTypeFor($index), immediate: false, causedByRider: true));
		}
	}

	private function broadcast(EntityLink $link) : void{
		$viewers = $this->owner->getViewers();
		if(count($viewers) === 0){
			return;
		}
		NetworkBroadcastUtils::broadcastPackets($viewers, [SetActorLinkPacket::create($link)]);
	}
}
