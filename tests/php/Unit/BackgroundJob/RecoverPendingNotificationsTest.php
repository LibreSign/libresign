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
		$mapper->expects($this->once())->method('findDue')->with(50)->willReturn([$delivery]);
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

}
