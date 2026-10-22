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

namespace pocketmine\resourcepacks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\event\player\PlayerResourcePackOfferEvent;
use pocketmine\network\mcpe\handler\ResourcePacksPacketHandler;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ResourcePackChunkDataPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkRequestPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\ResourcePackDataInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\types\ResourcePackClientResponseStatus;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackInfoEntry;
use pocketmine\player\PlayerInfo;
use pocketmine\promise\PromiseResolver;
use pocketmine\utils\Config;
use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function json_encode;
use function mkdir;
use function random_bytes;
use function rmdir;
use function str_repeat;
use function strtoupper;
use function sys_get_temp_dir;
use function unlink;
use const JSON_THROW_ON_ERROR;

final class ResourcePackCdnTest extends TestCase{
	private const PACK_ID = "65f47404-dace-4c01-9bde-849487ff71d2";
	private const LOCAL_PACK_ID = "a4e0c306-8a2d-496a-a8b7-653a6529e591";
	private const CDN_URL = "https://cdn.example.com/test.zip?version=1.0.0";
	private string $directory;

	protected function setUp() : void{
		$this->directory = sys_get_temp_dir() . "/axolotl-cdn-" . bin2hex(random_bytes(6));
		mkdir($this->directory);
		$archive = new \ZipArchive();
		self::assertTrue($archive->open($this->directory . "/test.zip", \ZipArchive::CREATE));
		$archive->addFromString("manifest.json", json_encode([
			"format_version" => 2,
			"header" => ["name" => "CDN test", "description" => "Test pack", "uuid" => self::PACK_ID, "version" => [1, 0, 0], "min_engine_version" => [1, 26, 0]],
			"modules" => [["type" => "resources", "uuid" => self::LOCAL_PACK_ID, "version" => [1, 0, 0]]]
		], JSON_THROW_ON_ERROR));
		$archive->close();
		file_put_contents($this->directory . "/test.zip.key", str_repeat("a", 32));
	}

	protected function tearDown() : void{
		unlink($this->directory . "/test.zip");
		unlink($this->directory . "/test.zip.key");
		unlink($this->directory . "/resource_packs.yml");
		rmdir($this->directory);
	}

	/** @param array<string, mixed> $cdnUrls */
	private function createManager(array $cdnUrls = [], ?\Logger $logger = null) : ResourcePackManager{
		$config = new Config($this->directory . "/resource_packs.yml", Config::YAML);
		$config->setAll(["force_resources" => true, "resource_stack" => ["test.zip"]]);
		if($cdnUrls !== []){
			$config->set("cdn_urls", $cdnUrls);
		}
		$config->save();
		return new ResourcePackManager($this->directory, $logger ?? self::createStub(\Logger::class));
	}

	public function testConfigAndRuntimeUrls() : void{
		$manager = $this->createManager(["test.zip" => self::CDN_URL]);
		self::assertSame(self::CDN_URL, $manager->getPackCdnUrl(strtoupper(self::PACK_ID)));
		self::assertSame(str_repeat("a", 32), $manager->getPackEncryptionKey(self::PACK_ID));
		$manager->setPackCdnUrl(strtoupper(self::PACK_ID), "http://cdn.example.com/test");
		self::assertSame("http://cdn.example.com/test", $manager->getPackCdnUrl(self::PACK_ID));
		$manager->setPackCdnUrl(self::PACK_ID, null);
		self::assertNull($manager->getPackCdnUrl(self::PACK_ID));
		$manager->setPackCdnUrl(self::LOCAL_PACK_ID, null);
	}

	public function testLegacyConfigUsesServerDownloads() : void{
		$manager = $this->createManager();
		self::assertCount(1, $manager->getResourceStack());
		self::assertNull($manager->getPackCdnUrl(self::PACK_ID));
	}

	/** @return iterable<string, array{string}> */
	public static function invalidUrls() : iterable{
		yield "empty" => [""];
		yield "relative" => ["test.zip"];
		yield "protocol-relative" => ["//cdn.example.com/test.zip"];
		yield "file" => ["file:///test.zip"];
		yield "ftp" => ["ftp://cdn.example.com/test.zip"];
		yield "missing host" => ["https://"];
		yield "unescaped whitespace" => ["https://cdn.example.com/test pack.zip"];
	}

	#[DataProvider("invalidUrls")]
	public function testInvalidUrlDoesNotReplaceCurrentUrl(string $url) : void{
		$manager = $this->createManager(["test.zip" => self::CDN_URL]);
		try{
			$manager->setPackCdnUrl(self::PACK_ID, $url);
			self::fail("Invalid URL was accepted");
		}catch(\InvalidArgumentException $e){
			self::assertSame("URL must be an absolute HTTP(S) URL", $e->getMessage());
		}
		self::assertSame(self::CDN_URL, $manager->getPackCdnUrl(self::PACK_ID));
	}

	public function testUnknownPackCannotReceiveUrl() : void{
		$manager = $this->createManager();
		$this->expectException(\InvalidArgumentException::class);
		$manager->setPackCdnUrl(self::LOCAL_PACK_ID, self::CDN_URL);
	}

	/** @return iterable<string, array{mixed}> */
	public static function invalidConfigUrls() : iterable{
		yield "wrong type" => [123];
		yield "unsupported scheme" => ["ftp://cdn.example.com/test.zip"];
	}

	#[DataProvider("invalidConfigUrls")]
	public function testInvalidConfigUrlKeepsLocalPack(mixed $url) : void{
		$logger = self::createMock(\Logger::class);
		$logger->expects(self::once())->method("warning")->with(self::stringContains("using server downloads"));
		$manager = $this->createManager(["test.zip" => $url], $logger);
		self::assertCount(1, $manager->getResourceStack());
		self::assertNull($manager->getPackCdnUrl(self::PACK_ID));
	}

	public function testMalformedCdnConfigIsRejected() : void{
		$config = new Config($this->directory . "/resource_packs.yml", Config::YAML);
		$config->setAll(["resource_stack" => ["test.zip"], "cdn_urls" => true]);
		$config->save();
		$this->expectException(\InvalidArgumentException::class);
		new ResourcePackManager($this->directory, self::createStub(\Logger::class));
	}

	public function testLegacyOfferAndHandlerUseServerDownloads() : void{
		$pack = $this->createManager()->getResourceStack()[0];
		$event = new PlayerResourcePackOfferEvent(self::createStub(PlayerInfo::class), [$pack], [], false);
		self::assertSame([], $event->getCdnUrls());
		$session = self::createMock(NetworkSession::class);
		$session->method("getLogger")->willReturn(self::createStub(\Logger::class));
		$session->expects(self::once())->method("sendDataPacket")->with(self::callback(static function(ResourcePacksInfoPacket $packet) : bool{
			self::assertSame("", $packet->resourcePackEntries[0]->getCdnUrl());
			return true;
		}))->willReturn(true);
		(new ResourcePacksPacketHandler($session, [$pack], [], false, static function() : void{}))->setUp();
	}

	public function testOfferCanOverrideAndResetUrls() : void{
		$pack = $this->createManager()->getResourceStack()[0];
		$key = str_repeat("a", 32);
		$event = new PlayerResourcePackOfferEvent(self::createStub(PlayerInfo::class), [$pack], [self::PACK_ID => $key], true, [self::PACK_ID => self::CDN_URL]);
		self::assertSame([self::PACK_ID => self::CDN_URL], $event->getCdnUrls());
		$event->setResourcePacks([$pack], [self::PACK_ID => $key], [self::PACK_ID => "https://cdn.example.com/override.zip"]);
		self::assertSame([self::PACK_ID => "https://cdn.example.com/override.zip"], $event->getCdnUrls());
		$event->setResourcePacks([$pack], [self::PACK_ID => $key]);
		self::assertSame([], $event->getCdnUrls());
		$event->addResourcePack($pack, $key, self::CDN_URL);
		self::assertSame([self::PACK_ID => self::CDN_URL], $event->getCdnUrls());
	}

	public function testMixedOfferPreservesMetadataAndSerializesUrl() : void{
		$pack = $this->createManager()->getResourceStack()[0];
		$local = self::createStub(ResourcePack::class);
		$local->method("getPackId")->willReturn(self::LOCAL_PACK_ID);
		$local->method("getPackVersion")->willReturn("2.0.0");
		$local->method("getPackSize")->willReturn(123);
		$key = str_repeat("a", 32);
		$session = self::createMock(NetworkSession::class);
		$session->method("getLogger")->willReturn(self::createStub(\Logger::class));
		$session->expects(self::once())->method("sendDataPacket")->with(self::callback(static function(ResourcePacksInfoPacket $packet) use ($pack, $key) : bool{
			self::assertTrue($packet->mustAccept);
			self::assertCount(2, $packet->resourcePackEntries);
			$entry = $packet->resourcePackEntries[0];
			self::assertSame(self::PACK_ID, $entry->getPackId()->toString());
			self::assertSame($pack->getPackVersion(), $entry->getVersion());
			self::assertSame($pack->getPackSize(), $entry->getSizeBytes());
			self::assertSame($key, $entry->getEncryptionKey());
			self::assertSame(self::PACK_ID, $entry->getContentId());
			self::assertSame(self::CDN_URL, $entry->getCdnUrl());
			$out = new ByteBufferWriter();
			$entry->write($out);
			$decoded = ResourcePackInfoEntry::read(new ByteBufferReader($out->getData()));
			self::assertSame(self::CDN_URL, $decoded->getCdnUrl());
			self::assertSame($key, $decoded->getEncryptionKey());
			self::assertSame("", $packet->resourcePackEntries[1]->getCdnUrl());
			return true;
		}))->willReturn(true);
		(new ResourcePacksPacketHandler($session, [$pack, $local], [self::PACK_ID => $key], true, static function() : void{}, [self::PACK_ID => self::CDN_URL]))->setUp();
	}

	public function testCdnPackCanStillBeDownloadedFromServer() : void{
		$pack = $this->createManager()->getResourceStack()[0];
		$data = file_get_contents($this->directory . "/test.zip");
		self::assertIsString($data);
		$session = self::createMock(NetworkSession::class);
		$session->method("getLogger")->willReturn(self::createStub(\Logger::class));
		$session->expects(self::once())->method("sendDataPacket")->with(self::callback(static function(ResourcePackDataInfoPacket $packet) use ($pack) : bool{
			self::assertSame($pack->getPackSize(), $packet->compressedPackSize);
			self::assertSame($pack->getSha256(), $packet->sha256);
			return true;
		}))->willReturn(true);
		/** @var PromiseResolver<null> $receipt */
		$receipt = new PromiseResolver();
		$session->expects(self::once())->method("sendDataPacketWithReceipt")->with(self::callback(static function(ResourcePackChunkDataPacket $packet) use ($data) : bool{
			self::assertSame($data, $packet->data);
			return true;
		}))->willReturn($receipt->getPromise());
		$handler = new ResourcePacksPacketHandler($session, [$pack], [], true, static function() : void{}, [self::PACK_ID => self::CDN_URL]);
		self::assertTrue($handler->handleResourcePackClientResponse(ResourcePackClientResponsePacket::create(ResourcePackClientResponseStatus::SEND_PACKS, [self::PACK_ID . "_1.0.0"])));
		self::assertTrue($handler->handleResourcePackChunkRequest(ResourcePackChunkRequestPacket::create(self::PACK_ID, 0)));
		$receipt->resolve(null);
	}
}
