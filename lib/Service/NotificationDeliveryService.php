<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\NotificationAttemptMapper;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Exception\MailTransportException;
use OCA\Libresign\Exception\NotificationPreparationException;
use OCA\Libresign\Service\Notification\NotificationChannel;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCP\AppFramework\Db\TTransactional;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

/**
 * Shared synchronous delivery ledger. Transport behavior belongs to the
 * channel adapters, not to this state machine. No async executor is enabled.
 *
 * A durable domain-level outbox must be staged inside the domain transaction
 * in a separate step. Do not call this dispatcher before the domain commits.
 */
class NotificationDeliveryService {
	use TTransactional;

	public function __construct(
		private NotificationDeliveryMapper $deliveries,
		private NotificationAttemptMapper $attempts,
		private IDBConnection $db,
		private ICrypto $crypto,
		private IConfig $config,
		private LoggerInterface $logger,
	) {
	}

	/** @return bool Whether this invocation claimed and attempted delivery. */
	public function dispatch(NotificationMessage $message, NotificationChannel $transport): bool {
		if ($this->db->inTransaction()) {
			throw new \LogicException('Notification dispatch during an open domain transaction is forbidden');
		}
		if (trim($message->recipient) === '' || $message->sourceEventId === '' || !$transport->supports($message->channel)) {
			throw new \InvalidArgumentException('Notification source, channel or recipient is invalid');
		}
		$secret = $this->config->getSystemValueString('secret');
		if ($secret === '') {
			throw new \LogicException('The Nextcloud instance secret is not available');
		}
		$recipient = trim($message->recipient);
		// Email addresses are case-insensitive for the LibreSign mail contract.
		// User IDs and gateway identifiers retain their own case semantics.
		if ($message->channel === 'mail') {
			$recipient = strtolower($recipient);
		}
		$correlationId = hash_hmac(
			'sha256',
			$message->sourceEventId . "\0" . $message->eventType
				. "\0" . $message->file->getId() . "\0" . $message->signRequest->getId(),
			hash_hmac('sha256', 'libresign-notification-event-v1', $secret, true),
		);
		$eventKey = hash_hmac(
			'sha256',
			$message->sourceEventId . "\0" . $message->eventType . "\0" . $message->channel
				. "\0" . $message->file->getId() . "\0" . $message->signRequest->getId()
				. "\0" . $recipient,
			hash_hmac('sha256', 'libresign-notification-v2', $secret, true),
		);
		$delivery = $this->deliveries->register(
			$eventKey,
			$correlationId,
			$message->eventType,
			$message->channel,
			$message->file->getId(),
			$message->signRequest->getId(),
			$message->file->getUserId(),
			$this->crypto->encrypt($message->recipient),
		);
		$attempt = $this->atomic(function () use ($delivery) {
			if (!$this->deliveries->claim($delivery->getId())) {
				return null;
			}
			return $this->attempts->begin($delivery->getId());
		}, $this->db);
		if ($attempt === null) {
			return false;
		}

		// Claim and attempt commit before the external side effect.
		$outcome = NotificationDeliveryState::UNKNOWN;
		$reason = 'unexpected_error';
		try {
			$transport->deliver($message);
			$outcome = NotificationDeliveryState::ACCEPTED;
			$reason = null;
		} catch (\Exception $e) {
			[$outcome, $reason] = $this->classify($e);
			$this->logger->warning('Notification transport was not confirmed', [
				'deliveryId' => $delivery->getId(),
				'channel' => $message->channel,
				'outcome' => $outcome->value,
				'reasonCode' => $reason,
			]);
			throw $e;
		} finally {
			// Unexpected PHP Errors also leave UNKNOWN, without swallowing them.
			$this->atomic(function () use ($delivery, $attempt, &$outcome, &$reason): void {
				$this->attempts->finish($attempt->getId(), $outcome, $reason);
				$this->deliveries->finish($delivery->getId(), $outcome);
			}, $this->db);
		}
		return true;
	}

	/** @return array{NotificationDeliveryState, string} */
	private function classify(\Exception $exception): array {
		$current = $exception;
		while ($current !== null) {
			if ($current instanceof NotificationPreparationException) {
				return [NotificationDeliveryState::TEMPORARY_FAILURE, $current->reasonCode];
			}
			if ($current instanceof MailTransportException) {
				return [$current->outcome, $current->reasonCode];
			}
			$current = $current->getPrevious();
		}
		return [NotificationDeliveryState::UNKNOWN, 'transport_outcome_unknown'];
	}
}
