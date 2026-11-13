<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Db;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

final class FileMapperCacheTest extends TestCase {
	public function testRollbackInvalidationEvictsIdentifierAliases(): void {
		$cache = $this->createMock(ICache::class);
		$removed = [];
		$cache->method('remove')->willReturnCallback(
			static function (string $key) use (&$removed): bool {
				$removed[] = $key;
				return true;
			},
		);
		$factory = $this->createMock(ICacheFactory::class);
		$factory->method('isAvailable')->willReturn(true);
		$factory->method('createDistributed')->willReturn($cache);
		$mapper = new FileMapper(
			$this->createMock(IDBConnection::class),
			$this->createMock(IL10N::class),
			$factory,
		);
		$file = new File();
		$file->setId(42);
		$file->setUuid('rolled-back-uuid');
		$file->setNodeId(77);

		$mapper->invalidateCachedEntity($file);

		$this->assertSame([
			'libresign_file:id:42',
			'libresign_file:node_id:77',
			'libresign_file:uuid:rolled-back-uuid',
		], $removed);
	}
}
