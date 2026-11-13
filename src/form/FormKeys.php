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

/**
 * JSON keys of the form data sent to the client.
 *
 * @internal
 */
final class FormKeys{

	private function __construct(){
		//NOOP
	}

	public const TYPE = "type";
	public const TITLE = "title";
	public const CONTENT = "content";
	public const ELEMENTS = "elements";
	public const BUTTON1 = "button1";
	public const BUTTON2 = "button2";

	public const TEXT = "text";
	public const TOOLTIP = "tooltip";
	public const DEFAULT = "default";
	public const PLACEHOLDER = "placeholder";
	public const MIN = "min";
	public const MAX = "max";
	public const STEP = "step";
	public const OPTIONS = "options";
	public const STEPS = "steps";

	public const IMAGE = "image";
	public const IMAGE_DATA = "data";
}
