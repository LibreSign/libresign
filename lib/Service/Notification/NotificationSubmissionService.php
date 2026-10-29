<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCA\Libresign\Db\NotificationAttemptMapper;
use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Exception\NotificationPreparationException;
use OCP\AppFramework\Db\TTransactional;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/** Manages one attempt. The claim commits before any external side effect. */
class NotificationSubmissionService {
	private const MAX_DELAY_SECONDS = 3600;
	use TTransactional;

	public function __construct(
		private NotificationDeliveryMapper $deliveries,
		private NotificationAttemptMapper $attempts,
		private IDBConnection $db,
		private NotificationFailureClassifier $failureClassifier,
		private NotificationHistoryService $history,
		private LoggerInterface $logger,
	) {
	}

	public function submit(NotificationDelivery $delivery, NotificationMessage $message, NotificationChannel $channel): bool {
		$attempt = $this->atomic(function () use ($delivery) {
			if (!$this->deliveries->claim($delivery->getId())) {
				return null;
			}
			return $this->attempts->begin($delivery->getId());
		}, $this->db);
		if ($attempt === null) {
			return false;
		}

		$outcome = NotificationDeliveryState::UNKNOWN;
		$reason = 'unexpected_error';
		$retrySafe = false;
		try {
			$channel->deliver($message);
			$outcome = NotificationDeliveryState::ACCEPTED;
			$reason = null;
			// This legacy projection is not allowed to turn transport acceptance
			// into an uncertain send or an invitation to retry SMTP.
			try {
				$this->history->recordAccepted($message);
			} catch (\Exception $error) {
				$reason = 'legacy_history_write_failed';
				$this->logger->warning('Notification accepted but legacy history could not be updated', [
					'deliveryId' => $delivery->getId(),
					'channel' => $message->channel,
					'errorType' => get_debug_type($error),
				]);
			}
		} catch (\Exception $e) {
			[$outcome, $reason] = $this->failureClassifier->classify($e);
			// Never treat a nested preparation error in an ambiguous transport failure as proof of non-submission.
			$retrySafe = $e instanceof NotificationPreparationException;
			if (!$retrySafe && $outcome === NotificationDeliveryState::TEMPORARY_FAILURE) {
				$outcome = NotificationDeliveryState::UNKNOWN;
				$reason = 'transport_outcome_unknown';
			}
			$this->logger->warning('Notification transport was not confirmed', [
				'deliveryId' => $delivery->getId(),
				'channel' => $message->channel,
				'outcome' => $outcome->value,
				'reasonCode' => $reason,
			]);
			throw $e;
		} finally {
			$this->atomic(function () use ($delivery, $attempt, &$outcome, &$reason, &$retrySafe): void {
				$retryAt = null;
				if ($retrySafe && $outcome === NotificationDeliveryState::TEMPORARY_FAILURE) {
					$count = $this->deliveries->findById($delivery->getId())->getAttemptCount();
					if ($count > 0 && $count < NotificationDeliveryMapper::MAX_ATTEMPTS) {
						$base = min(self::MAX_DELAY_SECONDS, 30 * (2 ** ($count - 1)));
						$retryAt = new \DateTime('now', new \DateTimeZone('UTC'));
						$retryAt->modify('+' . ($base + random_int(0, min(30, $base))) . ' seconds');
					} else {
						$outcome = NotificationDeliveryState::FAILED;
						$reason = 'retry_limit_exhausted';
					}
				}
				$this->attempts->finish($attempt->getId(), $outcome, $reason);
				$this->deliveries->finish($delivery->getId(), $outcome, $retryAt);
			}, $this->db);
		}
		return true;
	}
}
