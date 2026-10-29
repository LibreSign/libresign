<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\BackgroundJob;

use OCA\Libresign\BackgroundJob\DeliverNotification;
use OCA\Libresign\BackgroundJob\RecoverPendingNotifications;
use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\TestCase;

final class RecoverPendingNotificationsTest extends TestCase {
	public function testRecoveryOnlyQueuesDueDeliveriesWithoutRunningTransports(): void {
		$delivery = new NotificationDelivery();
		$delivery->setId(42);
		$mapper = $this->createMock(NotificationDeliveryMapper::class);
		$mapper->expects($this->once())->method('findDue')->with(50, 0)->willReturn([$delivery]);
		$jobs = $this->createMock(IJobList::class);
		$jobs->expects($this->once())->method('has')->with(DeliverNotification::class, ['id' => 42])->willReturn(false);
		$jobs->expects($this->once())->method('add')->with(DeliverNotification::class, ['id' => 42]);
		(new RecoverPendingNotifications($this->createMock(ITimeFactory::class), $mapper, $jobs))->run(null);
	}
	public function testRecoveryDoesNotResetLeaseOfExistingJob(): void {
		$delivery = new NotificationDelivery();
		$delivery->setId(43);
		$mapper = $this->createMock(NotificationDeliveryMapper::class);
		$mapper->method('findDue')->willReturn([$delivery]);
		$jobs = $this->createMock(IJobList::class);
		$jobs->expects($this->once())->method('has')->with(DeliverNotification::class, ['id' => 43])->willReturn(true);
		$jobs->expects($this->never())->method('add');
		(new RecoverPendingNotifications($this->createMock(ITimeFactory::class), $mapper, $jobs))->run(null);
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
			static fn (int $limit, int $offset): array => $offset === 0 ? $first : [$next],
		);
		$jobs = $this->createMock(IJobList::class);
		$jobs->method('has')->willReturnCallback(static fn (string $class, array $args): bool => $args['id'] !== 51);
		$jobs->expects($this->once())->method('add')->with(DeliverNotification::class, ['id' => 51]);
		(new RecoverPendingNotifications($this->createMock(ITimeFactory::class), $mapper, $jobs))->run(null);
	}

}
