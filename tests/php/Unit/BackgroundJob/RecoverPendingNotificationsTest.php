<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\BackgroundJob;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\BackgroundJob\DeliverNotification;
use OCA\Libresign\BackgroundJob\RecoverPendingNotifications;
use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;

final class RecoverPendingNotificationsTest extends TestCase {
	private function recover(NotificationDeliveryMapper $mapper, IJobList $jobs, ?IAppConfig $config = null): RecoverPendingNotifications {
		return new RecoverPendingNotifications($this->createMock(ITimeFactory::class), $mapper, $jobs, $config ?? $this->createMock(IAppConfig::class));
	}

	public function testRecoveryOnlyQueuesDueDeliveriesWithoutRunningTransports(): void {
		$delivery = new NotificationDelivery();
		$delivery->setId(42);
		$mapper = $this->createMock(NotificationDeliveryMapper::class);
		$mapper->expects($this->once())->method('findDue')->with(50, 0)->willReturn([$delivery]);
		$jobs = $this->createMock(IJobList::class);
		$jobs->expects($this->once())->method('has')->with(DeliverNotification::class, ['id' => 42])->willReturn(false);
		$jobs->expects($this->once())->method('add')->with(DeliverNotification::class, ['id' => 42]);
		($this->recover($mapper, $jobs))->run(null);
	}
	public function testRecoveryDoesNotResetLeaseOfExistingJob(): void {
		$delivery = new NotificationDelivery();
		$delivery->setId(43);
		$mapper = $this->createMock(NotificationDeliveryMapper::class);
		$mapper->method('findDue')->willReturn([$delivery]);
		$jobs = $this->createMock(IJobList::class);
		$jobs->expects($this->once())->method('has')->with(DeliverNotification::class, ['id' => 43])->willReturn(true);
		$jobs->expects($this->never())->method('add');
		($this->recover($mapper, $jobs))->run(null);
	}

	public function testQueuedFirstPageDoesNotStarveLaterDueJobs(): void {
		$first = [];
		for ($i = 1; $i <= 50; $i++) {
			$delivery = new NotificationDelivery();
			$delivery->setId($i);
			$first[] = $delivery;
		}
		$next = new NotificationDelivery();
		$next->setId(51);
		$mapper = $this->createMock(NotificationDeliveryMapper::class);
		$mapper->expects($this->exactly(2))->method('findDue')->willReturnCallback(
			static fn (int $limit, int $afterId): array => $afterId === 0 ? $first : [$next],
		);
		$jobs = $this->createMock(IJobList::class);
		$jobs->method('has')->willReturnCallback(static fn (string $class, array $args): bool => $args['id'] !== 51);
		$jobs->expects($this->once())->method('add')->with(DeliverNotification::class, ['id' => 51]);
		($this->recover($mapper, $jobs))->run(null);
	}

	public function testRecoveryWrapsCursorWhenNoHigherIdIsEligible(): void {
		$mapper = $this->createMock(NotificationDeliveryMapper::class);
		$mapper->expects($this->once())->method('findDue')->with(50, 500)->willReturn([]);
		$jobs = $this->createMock(IJobList::class);
		$jobs->expects($this->never())->method('add');
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueInt')->willReturn(500);
		$config->expects($this->once())->method('setValueInt')
			->with(Application::APP_ID, 'notification_recovery_last_scanned_id', 0);
		$this->recover($mapper, $jobs, $config)->run(null);
	}

	public function testQueueLimitDoesNotResetCursorBeforeScanningEntirePage(): void {
		$first = [];
		for ($id = 1; $id <= 50; $id++) {
			$delivery = new NotificationDelivery();
			$delivery->setId($id);
			$first[] = $delivery;
		}
		$second = [];
		foreach ([51, 52] as $id) {
			$delivery = new NotificationDelivery();
			$delivery->setId($id);
			$second[] = $delivery;
		}
		$mapper = $this->createMock(NotificationDeliveryMapper::class);
		$mapper->method('findDue')->willReturnCallback(
			static fn (int $limit, int $afterId): array => $afterId === 0 ? $first : $second,
		);
		$jobs = $this->createMock(IJobList::class);
		$jobs->method('has')->willReturnCallback(static fn (string $class, array $args): bool => $args['id'] === 50);
		$jobs->expects($this->exactly(50))->method('add');
		$config = $this->createMock(IAppConfig::class);
		$config->expects($this->once())->method('setValueInt')
			->with(Application::APP_ID, 'notification_recovery_last_scanned_id', 51);
		$this->recover($mapper, $jobs, $config)->run(null);
	}

	public function testPreviouslyQueuedFirstFiveHundredDoNotStarveLaterDeliveryAcrossCronRuns(): void {
		$due = [];
		for ($id = 1; $id <= 501; $id++) {
			$delivery = new NotificationDelivery();
			$delivery->setId($id);
			$due[] = $delivery;
		}
		$mapper = $this->createMock(NotificationDeliveryMapper::class);
		$mapper->method('findDue')->willReturnCallback(
			static fn (int $limit, int $afterId): array => array_slice($due, $afterId, $limit),
		);
		$jobs = $this->createMock(IJobList::class);
		$jobs->method('has')->willReturnCallback(static fn (string $class, array $args): bool => $args['id'] <= 500);
		$jobs->expects($this->once())->method('add')->with(DeliverNotification::class, ['id' => 501]);
		$cursor = 0;
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueInt')->with(Application::APP_ID, 'notification_recovery_last_scanned_id', 0)
			->willReturnCallback(static function () use (&$cursor): int {
				return $cursor;
			});
		$config->method('setValueInt')->willReturnCallback(
			static function (string $app, string $key, int $id) use (&$cursor): bool {
				$cursor = $id;
				return true;
			},
		);
		$this->recover($mapper, $jobs, $config)->run(null);
		$this->assertSame(500, $cursor);
		$this->recover($mapper, $jobs, $config)->run(null);
		$this->assertSame(0, $cursor);
	}

}
