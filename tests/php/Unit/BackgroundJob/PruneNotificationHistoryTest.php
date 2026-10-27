<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\BackgroundJob;

use OCA\Libresign\BackgroundJob\PruneNotificationHistory;
use OCA\Libresign\Db\NotificationAttemptMapper;
use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

final class PruneNotificationHistoryTest extends TestCase {
	public function testDoesNotDeleteRecordsWithoutExplicitRetentionConfiguration(): void {
		$attempts = $this->createMock(NotificationAttemptMapper::class);
		$deliveries = $this->createMock(NotificationDeliveryMapper::class);
		$attempts->expects($this->never())->method('deleteOlderThan');
		$deliveries->expects($this->never())->method('redactOlderThan');
		$deliveries->method('findStaleSending')->willReturn([]);
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('0');

		$job = new PruneNotificationHistory(
			$this->createMock(ITimeFactory::class),
			$attempts,
			$deliveries,
			$this->createMock(IDBConnection::class),
			$config,
		);
		$job->run(null);
	}

	public function testExplicitRetentionRedactsRatherThanDeletingEventKeys(): void {
		$attempts = $this->createMock(NotificationAttemptMapper::class);
		$deliveries = $this->createMock(NotificationDeliveryMapper::class);
		$deliveries->method('findStaleSending')->willReturn([]);
		$attempts->expects($this->once())->method('deleteOlderThan')
			->with($this->isInstanceOf(\DateTime::class), 500)->willReturn(0);
		$deliveries->expects($this->once())->method('redactOlderThan')
			->with($this->isInstanceOf(\DateTime::class), 500)->willReturn(0);
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('365');

		(new PruneNotificationHistory(
			$this->createMock(ITimeFactory::class),
			$attempts, $deliveries,
			$this->createMock(IDBConnection::class),
			$config,
		))->run(null);
	}

	public function testStaleSubmissionIsClassifiedUnknownWithoutAnyRetry(): void {
		$delivery = new NotificationDelivery();
		$delivery->setId(17);
		$deliveries = $this->createMock(NotificationDeliveryMapper::class);
		$deliveries->method('findStaleSending')->willReturn([$delivery]);
		$deliveries->method('markStaleUnknown')->willReturn(true);
		$deliveries->expects($this->never())->method('claim');
		$attempts = $this->createMock(NotificationAttemptMapper::class);
		$attempts->expects($this->once())->method('finishInterrupted')->with(17);
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->once())->method('beginTransaction');
		$db->expects($this->once())->method('commit');
		$db->expects($this->never())->method('rollBack');

		(new PruneNotificationHistory(
			$this->createMock(ITimeFactory::class),
			$attempts, $deliveries, $db,
			$this->createMock(IConfig::class),
		))->run(null);
	}
}
