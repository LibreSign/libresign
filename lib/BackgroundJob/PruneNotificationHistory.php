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
use OCP\IConfig;
use OCP\IDBConnection;

/**
 * Reconcile abandoned attempts and optionally redact old notification evidence.
 * This job does not send or retry messages; an ambiguous SMTP result stays unknown.
 */
final class PruneNotificationHistory extends TimedJob {
	use TTransactional;
	private const BATCH_SIZE = 500;

	public function __construct(
		ITimeFactory $time,
		private NotificationAttemptMapper $attempts,
		private NotificationDeliveryMapper $deliveries,
		private IDBConnection $db,
		private IConfig $config,
	) {
		parent::__construct($time);
		$this->setInterval(86400);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	#[\Override]
	public function run($argument): void {
		$now = new \DateTime('now', new \DateTimeZone('UTC'));

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

		// There is no implicit time-based deletion of notification evidence.
		// A configured limit removes old attempt details and redacts personal
		// fields, preserving permanent event-id tombstones for idempotency.
		$retentionDays = (int)$this->config->getAppValue('libresign', 'notification_history_retention_days', '0');
		if ($retentionDays <= 0) {
			return;
		}
		$cutoff = (clone $now)->modify('-' . $retentionDays . ' days');

		// Bound the job runtime to keep background jobs responsive.
		$deadline = microtime(true) + 10;
		do {
			$count = $this->attempts->deleteOlderThan($cutoff, self::BATCH_SIZE);
		} while ($count === self::BATCH_SIZE && microtime(true) < $deadline);

		do {
			$count = $this->deliveries->redactOlderThan($cutoff, self::BATCH_SIZE);
		} while ($count === self::BATCH_SIZE && microtime(true) < $deadline);
	}
}
