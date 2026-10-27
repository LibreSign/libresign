<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\BackgroundJob;

use OCA\Libresign\Db\NotificationAttemptMapper;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCP\AppFramework\Db\TTransactional;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use OCP\IDBConnection;

/**
 * Audit retention only. This job does not send or retry notifications.
 * All data is already terminal or old enough to expire by retention policy.
 */
final class PruneNotificationHistory extends TimedJob {
	use TTransactional;
	private const BATCH_SIZE = 500;

	public function __construct(
		ITimeFactory $time,
		private NotificationAttemptMapper $attempts,
		private NotificationDeliveryMapper $deliveries,
		private IDBConnection $db,
	) {
		parent::__construct($time);
		$this->setInterval(86400);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	#[\Override]
	public function run($argument): void {
		$now = new \DateTime('now', new \DateTimeZone('UTC'));
		$attemptCutoff = (clone $now)->modify('-90 days');
		$deliveryCutoff = (clone $now)->modify('-365 days');

		// The absence of a process after a full day cannot establish whether
		// SMTP accepted the message. Reconcile it to UNKNOWN, never retry.
		$interruptedBefore = (clone $now)->modify('-1 day');
		foreach ($this->deliveries->findStaleSending($interruptedBefore) as $delivery) {
			$this->atomic(function () use ($delivery, $interruptedBefore): void {
				if ($this->deliveries->markStaleUnknown($delivery->getId(), $interruptedBefore)) {
					$this->attempts->finishInterrupted($delivery->getId());
				}
			}, $this->db);
		}

		// Bound the job runtime to keep background jobs responsive. Another
		// daily run continues from the oldest remaining records.
		$deadline = microtime(true) + 10;
		do {
			$count = $this->attempts->deleteOlderThan($attemptCutoff, self::BATCH_SIZE);
		} while ($count === self::BATCH_SIZE && microtime(true) < $deadline);

		do {
			$count = $this->deliveries->deleteOlderThan($deliveryCutoff, self::BATCH_SIZE);
		} while ($count === self::BATCH_SIZE && microtime(true) < $deadline);
	}
}
