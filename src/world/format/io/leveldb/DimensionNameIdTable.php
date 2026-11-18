<?php

declare(strict_types=1);

namespace pocketmine\world\format\io\leveldb;

use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\exception\CorruptedWorldException;
use function count;
use function max;

/**
 * Maps custom dimension names to the numeric IDs used in chunk keys, using the same key and rules as vanilla, so
 * that IDs never change once assigned and worlds stay readable by vanilla.
 */
final class DimensionNameIdTable{
	private const KEY = "DimensionNameIdTable";
	private const TAG_ENTRIES = "entries";

	private const MIN_ID = 1000;
	private const MAX_ID = 65534;

	/**
	 * @var int[]
	 * @phpstan-var array<string, int>
	 */
	private array $ids = [];

	public function __construct(
		private \LevelDB $db
	){
		$raw = $db->get(self::KEY);
		if($raw === false){
			return;
		}
		try{
			$root = (new LittleEndianNbtSerializer())->read($raw)->mustGetCompoundTag();
		}catch(NbtDataException $e){
			throw new CorruptedWorldException("Invalid " . self::KEY . ": " . $e->getMessage(), 0, $e);
		}
		$entries = $root->getCompoundTag(self::TAG_ENTRIES);
		if($entries === null){
			return;
		}
		foreach(Utils::promoteKeys($entries->getValue()) as $name => $tag){
			if($tag instanceof IntTag){
				$this->ids[(string) $name] = $tag->getValue();
			}
		}
	}

	/**
	 * Returns the ID stored for the given name, or assigns the next unused ID and writes the table immediately, so
	 * that chunks saved under the new ID can never outlive the mapping.
	 */
	public function getOrAllocate(string $name) : int{
		if(isset($this->ids[$name])){
			return $this->ids[$name];
		}
		$id = count($this->ids) > 0 ? max(self::MIN_ID - 1, max($this->ids)) + 1 : self::MIN_ID;
		if($id > self::MAX_ID){
			throw new \OverflowException("No dimension IDs left for $name");
		}
		$this->ids[$name] = $id;
		$this->save();
		return $id;
	}

	private function save() : void{
		$entries = new CompoundTag();
		foreach(Utils::stringifyKeys($this->ids) as $name => $id){
			$entries->setInt($name, $id);
		}
		$root = CompoundTag::create()->setTag(self::TAG_ENTRIES, $entries);
		$this->db->put(self::KEY, (new LittleEndianNbtSerializer())->write(new TreeRoot($root)));
	}
}
