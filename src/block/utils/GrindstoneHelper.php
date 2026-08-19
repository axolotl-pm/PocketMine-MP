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

use pocketmine\crafting\GrindstoneCraftResult;
use pocketmine\item\Durable;
use pocketmine\item\EnchantedBook;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use function intdiv;
use function max;
use function mt_rand;

final class GrindstoneHelper{
	private const REPAIR_BONUS_PERCENT = 5;

	private function __construct(){
		//NOOP
	}

	public static function calculateResult(?Item $first, ?Item $second) : ?GrindstoneCraftResult{
		$output = self::calculateOutput($first, $second);
		if($output === null){
			return null;
		}

		$first = self::normalize($first);
		$second = self::normalize($second);
		$experience = 0;
		if($first !== null){
			$experience += self::getExperience($first);
		}
		if($second !== null){
			$experience += self::getExperience($second);
		}
		if($experience > 0){
			$experience = intdiv($experience + 1, 2);
			$experience += mt_rand(0, $experience - 1);
		}

		return new GrindstoneCraftResult($output, $experience);
	}

	public static function calculateOutput(?Item $first, ?Item $second) : ?Item{
		$first = self::normalize($first);
		$second = self::normalize($second);
		if($first === null){
			return $second === null ? null : self::calculateSingleOutput($second);
		}
		if($second === null){
			return self::calculateSingleOutput($first);
		}
		if(
			$first->getCount() !== 1 ||
			$second->getCount() !== 1 ||
			!$first instanceof Durable ||
			!$second instanceof Durable ||
			$first->getTypeId() !== $second->getTypeId()
		){
			return null;
		}

		$output = clone $first;
		self::removeNonCurses($output);
		self::preserveCurses($output, $second);
		$remainingDurability =
			$first->getMaxDurability() - $first->getDamage() +
			$second->getMaxDurability() - $second->getDamage() +
			intdiv($first->getMaxDurability() * self::REPAIR_BONUS_PERCENT, 100);
		$output->setDamage(max($first->getMaxDurability() - $remainingDurability, 0));

		return $output;
	}

	private static function normalize(?Item $item) : ?Item{
		return $item === null || $item->isNull() ? null : $item;
	}

	private static function calculateSingleOutput(Item $input) : ?Item{
		//see https://bugs.mojang.com/browse/MCPE-54256
		if($input->getCount() !== 1 || (!$input->hasEnchantments() && !$input instanceof Durable)){
			return null;
		}

		$output = clone $input;
		self::removeNonCurses($output);
		if($input instanceof EnchantedBook && !$output->hasEnchantments()){
			return VanillaItems::BOOK()->setCount($output->getCount())->setNamedTag($output->getNamedTag());
		}

		return $output;
	}

	private static function removeNonCurses(Item $item) : void{
		foreach($item->getEnchantments() as $enchantment){
			if(!self::isCurse($enchantment)){
				$item->removeEnchantment($enchantment->getType());
			}
		}
	}

	private static function preserveCurses(Item $target, Item $source) : void{
		foreach($source->getEnchantments() as $enchantment){
			if(self::isCurse($enchantment) && !$target->hasEnchantment($enchantment->getType())){
				$target->addEnchantment($enchantment);
			}
		}
	}

	private static function isCurse(EnchantmentInstance $enchantment) : bool{
		return $enchantment->getType() === VanillaEnchantments::VANISHING(); //TODO: others
	}

	private static function getExperience(Item $item) : int{
		$experience = 0;
		foreach($item->getEnchantments() as $enchantment){
			if(!self::isCurse($enchantment)){
				$experience += $enchantment->getType()->getMinEnchantingPower($enchantment->getLevel());
			}
		}

		return $experience;
	}
}
