<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Install;

use OCA\Libresign\Service\Install\DownloadProgressReporter;
use OCA\Libresign\Service\Install\InstallProgressStore;
use OCA\Libresign\Service\Install\InstallTarget;
use PHPUnit\Framework\TestCase;

final class DownloadProgressReporterTest extends TestCase {
	public function testCoalescesUpdatesButPublishesInitialAndFinalProgress(): void {
		$now = 10.0;
		$writes = [];
		$target = InstallTarget::from('x86_64', 'linux');
		$store = $this->createMock(InstallProgressStore::class);
		$store->method('get')->willReturn(['pid' => 123]);
		$store->method('set')->willReturnCallback(
			static function (InstallTarget $_target, string $_resource, array $data) use (&$writes): void {
				$writes[] = $data;
			},
		);
		$reporter = new DownloadProgressReporter($store, $target, 'java', static function () use (&$now): float {
			return $now;
		});

		$reporter->report(100, 0);
		$now = 10.1;
		$reporter->report(100, 10);
		$now = 10.2;
		$reporter->report(100, 20);
		self::assertCount(1, $writes);

		$now = 10.5;
		$reporter->report(100, 40);
		$now = 10.6;
		$reporter->report(100, 100);
		$reporter->flush();

		self::assertSame([
			['pid' => 123, 'download_size' => 100, 'downloaded' => 0],
			['pid' => 123, 'download_size' => 100, 'downloaded' => 40],
			['pid' => 123, 'download_size' => 100, 'downloaded' => 100],
		], $writes);
	}

	public function testFlushPersistsLastProgressWhenTransferStopsBeforeCompletion(): void {
		$now = 1.0;
		$writes = [];
		$store = $this->createMock(InstallProgressStore::class);
		$store->method('get')->willReturn([]);
		$store->method('set')->willReturnCallback(
			static function (InstallTarget $_target, string $_resource, array $data) use (&$writes): void {
				$writes[] = $data;
			},
		);
		$reporter = new DownloadProgressReporter(
			$store,
			InstallTarget::from('aarch64', 'linux'),
			'cfssl',
			static function () use (&$now): float { return $now; },
		);
		$reporter->report(0, 0);
		$now = 1.05;
		$reporter->report(100, 30);
		$reporter->flush();
		$reporter->flush();
		self::assertSame([
			['download_size' => 0, 'downloaded' => 0],
			['download_size' => 100, 'downloaded' => 30],
		], $writes);
	}
}
