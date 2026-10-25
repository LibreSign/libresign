<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\Service\Worker\LocalWorkerStateStore;
use PHPUnit\Framework\TestCase;

class LocalWorkerStateStoreTest extends TestCase {
	private string $directory;

	protected function setUp(): void {
		if (PHP_OS_FAMILY !== 'Linux') {
			$this->markTestSkipped('Local worker state uses POSIX filesystem');
		}
		$this->directory = sys_get_temp_dir() . '/libresign-worker-state-' . bin2hex(random_bytes(12));
	}

	protected function tearDown(): void {
		foreach (glob($this->directory . '/*') ?: [] as $path) {
			@unlink($path);
		}
		@chmod($this->directory, 0700);
		@rmdir($this->directory);
	}

	public function testStateSurvivesSeparateRequests(): void {
		$first = new LocalWorkerStateStore($this->directory);
		$this->assertTrue($first->withExclusiveLock(static function () use ($first): bool {
			$first->writeState(['workers' => [['pid' => 42, 'start' => 'start-token']]]);
			return true;
		}));
		$second = new LocalWorkerStateStore($this->directory);
		$this->assertTrue($second->withExclusiveLock(function () use ($second): bool {
			$this->assertCount(1, $second->readState()['workers']);
			return true;
		}));
	}

	public function testRejectsUnsafeRegistryDirectory(): void {
		mkdir($this->directory, 0700);
		chmod($this->directory, 0777);
		$this->expectException(\RuntimeException::class);
		(new LocalWorkerStateStore($this->directory))->withExclusiveLock(static fn (): bool => true);
	}

	public function testRejectsSymlinkLock(): void {
		mkdir($this->directory, 0700);
		file_put_contents($this->directory . '/target', 'unchanged');
		symlink($this->directory . '/target', $this->directory . '/lock');
		$this->expectException(\RuntimeException::class);
		try {
			(new LocalWorkerStateStore($this->directory))->withExclusiveLock(static fn (): bool => true);
		} finally {
			$this->assertSame('unchanged', file_get_contents($this->directory . '/target'));
		}
	}
}
