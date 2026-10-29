<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Enum\SignRequestStatus;
use OCP\AppFramework\Db\DoesNotExistException;
use Psr\Log\LoggerInterface;

/**
 * Process a durable notification. Only PENDING records are eligible.
 * A claimed transport attempt is never retried after an uncertain outcome.
 */
final class NotificationDeliveryProcessor {
	public function __construct(
		private NotificationDeliveryMapper $deliveries,
		private SignRequestMapper $signRequests,
		private NotificationEnvelopeCodec $codec,
		private NotificationTransportRegistry $transports,
		private NotificationSubmissionService $submission,
		private LoggerInterface $logger,
	) {
	}

	public function process(int $deliveryId): void {
		try {
			$delivery = $this->deliveries->findById($deliveryId);
		} catch (DoesNotExistException) {
			// An old queued job may remain after the retention job deleted its row.
			return;
		}
		if ($delivery->getState() !== NotificationDeliveryState::PENDING->value
			&& ($delivery->getState() !== NotificationDeliveryState::TEMPORARY_FAILURE->value
				|| $delivery->getNextAttemptAt() === null
				|| $delivery->getNextAttemptAt() > new \DateTime('now', new \DateTimeZone('UTC')))) {
			return;
		}
		if ($delivery->getEventType() === 'sign_request') {
			// Never resurrect invitations for removed, rejected or already-signed
			// participants. Cancellation notices use their stored snapshot instead.
			try {
				$request = $this->signRequests->getByIdUncached($delivery->getSignRequestId());
				$actionable = $request->getSigned() === null && in_array(
					$request->getStatusEnum(),
					[SignRequestStatus::ABLE_TO_SIGN, SignRequestStatus::OBSERVING],
					true,
				);
			} catch (DoesNotExistException) {
				$actionable = false;
			}
			if (!$actionable) {
				$this->deliveries->cancelPendingInvitations($delivery->getSignRequestId());
				return;
			}
		}
		try {
			$message = $this->codec->decode($delivery);
			$transport = $this->transports->resolve($message->channel);
		} catch (\Throwable $error) {
			$this->deliveries->failPending($deliveryId);
			$this->logger->warning('Unable to prepare queued notification', [
				'deliveryId' => $deliveryId,
				'exceptionType' => get_debug_type($error),
			]);
			return;
		}
		try {
			$this->submission->submit($delivery, $message, $transport);
		} catch (\Exception $error) {
			// submit() has already recorded the definitive or unknown outcome.
			// Exceptions must not interfere with processing another delivery.
			$this->logger->warning('Queued notification transport failed', [
				'deliveryId' => $deliveryId,
				'exceptionType' => get_debug_type($error),
			]);
		}
	}
}
