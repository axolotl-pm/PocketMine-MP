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

namespace pocketmine\network\mcpe\nethernet;

use pocketmine\nethernet\discovery\ServerDataProvider;
use pocketmine\nethernet\negotiation\Negotiator;
use pocketmine\nethernet\signaling\http\ServerStatusProvider;

/**
 * Context provided to {@link NetherNetSignalingFactory} when constructing signaling transports on the NetherNet thread.
 */
final class NetherNetSignalingContext{

	public function __construct(
		private Negotiator $negotiator,
		private ServerStatusProvider $statusProvider,
		private ServerDataProvider $serverDataProvider,
		private \Logger $logger
	){}

	public function getNegotiator() : Negotiator{ return $this->negotiator; }

	public function getStatusProvider() : ServerStatusProvider{ return $this->statusProvider; }

	public function getServerDataProvider() : ServerDataProvider{ return $this->serverDataProvider; }

	public function getLogger() : \Logger{ return $this->logger; }
}
