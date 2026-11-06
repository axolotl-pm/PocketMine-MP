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

namespace pocketmine\form;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pocketmine\player\Player;
use function json_decode;
use function json_encode;
use const INF;
use const JSON_THROW_ON_ERROR;
use const NAN;

final class FormTest extends TestCase{

	/** @param \Closure(Player, mixed) : void $callback */
	private static function makeForm(string $type, \Closure $callback) : Form{
		return match($type){
			"simple" => new SimpleForm("Menu", "Choose", [new FormButton("One"), new FormButton("Two", "textures/items/diamond")], static function(Player $player, ?int $selection) use ($callback) : void{ $callback($player, $selection); }),
			"modal" => new ModalForm("Confirm", "Continue?", "Yes", "No", static function(Player $player, ?bool $selection) use ($callback) : void{ $callback($player, $selection); }),
			"custom" => new CustomForm("Settings", [
				FormElement::label("Profile"),
				FormElement::input("Name", "Your name", "Steve"),
				FormElement::toggle("Enabled", true),
				FormElement::slider("Volume", 0, 10, 0.5, 5),
				FormElement::dropdown("Color", ["Red", "Blue"], 1),
				FormElement::stepSlider("Size", ["Small", "Large"])
			], static function(Player $player, ?array $values) use ($callback) : void{ $callback($player, $values); }),
			default => throw new \InvalidArgumentException("Unknown form type")
		};
	}

	public function testSerialization() : void{
		$callback = static function(Player $player, mixed $response) : void{};
		self::assertSame([
			"type" => "form", "title" => "Menu", "content" => "Choose", "buttons" => [
				["text" => "One"],
				["text" => "Two", "image" => ["type" => "path", "data" => "textures/items/diamond"]]
			]
		], json_decode(json_encode(self::makeForm("simple", $callback), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
		self::assertSame([
			"type" => "modal", "title" => "Confirm", "content" => "Continue?", "button1" => "Yes", "button2" => "No"
		], self::makeForm("modal", $callback)->jsonSerialize());
		self::assertSame([
			"type" => "custom_form", "title" => "Settings", "content" => [
				["type" => "label", "text" => "Profile"],
				["type" => "input", "text" => "Name", "placeholder" => "Your name", "default" => "Steve"],
				["type" => "toggle", "text" => "Enabled", "default" => true],
				["type" => "slider", "text" => "Volume", "min" => 0, "max" => 10, "step" => 0.5, "default" => 5],
				["type" => "dropdown", "text" => "Color", "options" => ["Red", "Blue"], "default" => 1],
				["type" => "step_slider", "text" => "Size", "steps" => ["Small", "Large"], "default" => 0]
			]
		], json_decode(json_encode(self::makeForm("custom", $callback), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
		self::assertSame(["text" => "Website", "image" => ["type" => "url", "data" => "https://example.com/icon.png"]], (new FormButton("Website", "https://example.com/icon.png"))->jsonSerialize());
	}

	/** @return iterable<string, array{string, mixed}> */
	public static function validResponses() : iterable{
		yield "first button" => ["simple", 0];
		yield "last button" => ["simple", 1];
		yield "first modal button" => ["modal", true];
		yield "second modal button" => ["modal", false];
		yield "all controls" => ["custom", [null, "Alex", false, 7.5, 0, 1]];
		yield "integer slider" => ["custom", [null, "", true, 0, 1, 0]];
		foreach(["simple", "modal", "custom"] as $type){
			yield "$type canceled" => [$type, null];
		}
	}

	#[DataProvider("validResponses")]
	public function testValidResponse(string $type, mixed $response) : void{
		$player = FormTestPlayer::create(self::createStub(\Logger::class));
		$calls = 0;
		$form = self::makeForm($type, static function(Player $actualPlayer, mixed $actualResponse) use ($player, $response, &$calls) : void{
			self::assertSame($player, $actualPlayer);
			self::assertSame($response, $actualResponse);
			++$calls;
		});
		$form->handleResponse($player, $response);
		self::assertSame(1, $calls);
	}

	/** @return iterable<string, array{string, mixed}> */
	public static function invalidResponses() : iterable{
		foreach([-1, 2, "0", 0.0, false, []] as $index => $value){
			yield "simple $index" => ["simple", $value];
		}
		foreach([0, 1, "true", []] as $index => $value){
			yield "modal $index" => ["modal", $value];
		}
		foreach([false, [], [null], [null, "Alex", true, 5, 0, 0, null], [1 => null, 2 => "Alex", 3 => true, 4 => 5, 5 => 0, 6 => 0], (object) [0 => null, 1 => "Alex", 2 => true, 3 => 5, 4 => 0, 5 => 0]] as $index => $value){
			yield "custom shape $index" => ["custom", $value];
		}
		foreach([0 => "label", 1 => 123, 2 => 1, 3 => 11, 4 => -1, 5 => 2] as $index => $value){
			$response = [null, "Alex", true, 5, 0, 0];
			$response[$index] = $value;
			yield "custom element $index" => ["custom", $response];
		}
	}

	#[DataProvider("invalidResponses")]
	public function testInvalidResponseDoesNotCallPlugin(string $type, mixed $response) : void{
		$form = self::makeForm($type, static function(Player $player, mixed $response) : void{
			self::fail("Invalid responses must not reach the plugin");
		});
		$this->expectException(FormValidationException::class);
		$form->handleResponse(FormTestPlayer::create(self::createStub(\Logger::class)), $response);
	}

	/** @return iterable<string, array{FormElement, mixed}> */
	public static function invalidElementValues() : iterable{
		foreach(["5", true, -0.1, 10.1, INF, NAN, null] as $index => $value){
			yield "slider $index" => [FormElement::slider("Value", 0, 10), $value];
		}
		foreach(["0", 0.0, true, -1, 2, null] as $index => $value){
			yield "dropdown $index" => [FormElement::dropdown("Value", ["A", "B"]), $value];
			yield "step slider $index" => [FormElement::stepSlider("Value", ["A", "B"]), $value];
		}
	}

	#[DataProvider("invalidElementValues")]
	public function testInvalidElementValue(FormElement $element, mixed $value) : void{
		$this->expectException(FormValidationException::class);
		$element->validateResponse($value);
	}

	/** @return iterable<string, array{\Closure() : FormElement}> */
	public static function invalidConfigurations() : iterable{
		yield "reversed range" => [static fn() => FormElement::slider("Value", 10, 0)];
		yield "zero step" => [static fn() => FormElement::slider("Value", 0, 10, 0)];
		yield "negative step" => [static fn() => FormElement::slider("Value", 0, 10, -1)];
		yield "default below min" => [static fn() => FormElement::slider("Value", 0, 10, 1, -1)];
		yield "default above max" => [static fn() => FormElement::slider("Value", 0, 10, 1, 11)];
		yield "infinite min" => [static fn() => FormElement::slider("Value", -INF, 10)];
		yield "infinite max" => [static fn() => FormElement::slider("Value", 0, INF)];
		yield "nan step" => [static fn() => FormElement::slider("Value", 0, 10, NAN)];
		yield "nan default" => [static fn() => FormElement::slider("Value", 0, 10, 1, NAN)];
		yield "empty options" => [static fn() => FormElement::dropdown("Value", [])];
		yield "negative default" => [static fn() => FormElement::dropdown("Value", ["A"], -1)];
		yield "missing default" => [static fn() => FormElement::stepSlider("Value", ["A"], 1)];
	}

	/** @param \Closure() : FormElement $factory */
	#[DataProvider("invalidConfigurations")]
	public function testInvalidConfiguration(\Closure $factory) : void{
		$this->expectException(\InvalidArgumentException::class);
		$factory();
	}

	public function testCallbackMustAcceptCancellation() : void{
		$this->expectException(\TypeError::class);
		// @phpstan-ignore argument.type (The test deliberately supplies an incompatible callback.)
		new SimpleForm("Menu", "", [], static function(Player $player, int $selection) : void{});
	}

	public function testEmptyCustomForm() : void{
		$form = new CustomForm("Empty", [], static function(Player $player, ?array $values) : void{
			self::assertSame([], $values);
		});
		$form->handleResponse(FormTestPlayer::create(self::createStub(\Logger::class)), []);
	}

	public function testConfigurationUsesIndependentLists() : void{
		$options = [5 => "A", 8 => "B"];
		$element = FormElement::dropdown("Value", $options, 1);
		$options[8] = "Changed";
		self::assertSame(["A", "B"], $element->jsonSerialize()["options"]);
	}
}
