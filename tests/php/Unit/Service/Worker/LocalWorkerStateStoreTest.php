<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\Service\Worker\LocalWorkerStateStore;
use OCA\Libresign\Service\Worker\WorkerConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
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

	public function testSigningAndNotificationPoolsHaveIndependentLocksAndState(): void {
		$store = new LocalWorkerStateStore($this->directory);
		$notificationEntered = false;
		$this->assertTrue($store->withExclusiveLock(function () use ($store, &$notificationEntered): bool {
			$store->writeState(['workers' => [['pid' => 101, 'start' => 'signer']]], WorkerConfiguration::SIGNING);
			return $store->withExclusiveLock(function () use ($store, &$notificationEntered): bool {
				$notificationEntered = true;
				$store->writeState(['workers' => [['pid' => 202, 'start' => 'notifier']]], WorkerConfiguration::NOTIFICATION);
				return true;
			}, WorkerConfiguration::NOTIFICATION);
		}, WorkerConfiguration::SIGNING));
		$this->assertTrue($notificationEntered, 'An active signing lock cannot block the notification pool');
		$this->assertSame(101, $store->readState(WorkerConfiguration::SIGNING)['workers'][0]['pid']);
		$this->assertSame(202, $store->readState(WorkerConfiguration::NOTIFICATION)['workers'][0]['pid']);
	}

	public function testContendedLockDoesNotRunTheSecondOperation(): void {
		$first = new LocalWorkerStateStore($this->directory);
		$second = new LocalWorkerStateStore($this->directory);
		$secondWasCalled = false;
		$this->assertTrue($first->withExclusiveLock(function () use ($second, &$secondWasCalled): bool {
			$this->assertTrue($second->withExclusiveLock(static function () use (&$secondWasCalled): bool {
				$secondWasCalled = true;
				return false;
			}));
			return true;
		}));
		$this->assertFalse($secondWasCalled, 'Contention must not launch a duplicate worker');
	}

	public function testLockIsReleasedAfterAnOperationFails(): void {
		$state = new LocalWorkerStateStore($this->directory);
		try {
			$state->withExclusiveLock(static function (): bool {
				throw new \RuntimeException('Temporary worker failure');
			});
			$this->fail('The error must be propagated');
		} catch (\RuntimeException $e) {
			$this->assertSame('Temporary worker failure', $e->getMessage());
		}
		$this->assertTrue($state->withExclusiveLock(static fn (): bool => true));
	}

	/** @return array<string, array{array<string, mixed>}> */
	public static function invalidWorkerStates(): array {
		return [
			'invalid PID' => [['workers' => [['pid' => 'invalid', 'start' => 'old-process']]]],
			'missing process identity' => [['workers' => [['pid' => 22]]]],
			'not a worker list' => [['workers' => ['worker' => ['pid' => 22, 'start' => '123']]]],
		];
	}

	#[DataProvider('invalidWorkerStates')]
	public function testCorruptWorkerRecordsDoNotPermitExtraStarts(array $invalidState): void {
		$state = new LocalWorkerStateStore($this->directory);
		$state->withExclusiveLock(static function () use ($state, $invalidState): bool {
			$state->writeState($invalidState);
			return true;
		});
		$this->expectException(\RuntimeException::class);
		$state->readState();
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
