<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\NotificationAttemptMapper;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Exception\MailTransportException;
use OCA\Libresign\Exception\NotificationPreparationException;
use OCP\AppFramework\Db\TTransactional;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

/**
 * Durable record for synchronous mail. This is not an async executor.
 *
 * Do not call while a domain transaction is open. Sending SMTP before commit
 * would expose an event that may still roll back. The next stage must stage
 * domain events inside the transaction and dispatch them after commit.
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

	/**
	 * @param callable(): void $send Actual synchronous mail submission.
	 * @return bool Whether this invocation claimed and attempted the delivery.
	 */
	public function send(
		string $sourceEventId,
		string $eventType,
		FileEntity $file,
		SignRequest $request,
		string $email,
		callable $send,
	): bool {
		if ($this->db->inTransaction()) {
			throw new \LogicException('SMTP notification dispatch during an open database transaction is forbidden');
		}
		if ($email === '' || $sourceEventId === '') {
			throw new \InvalidArgumentException('Notification source and recipient must be identified');
		}
		$secret = $this->config->getSystemValueString('secret');
		if ($secret === '') {
			throw new \LogicException('The Nextcloud instance secret is not available');
		}
		$eventKey = hash_hmac(
			'sha256',
			$sourceEventId . "\0" . $eventType . "\0" . $file->getId()
				. "\0" . $request->getId() . "\0" . strtolower(trim($email)),
			hash_hmac('sha256', 'libresign-notification-v1', $secret, true),
		);
		$delivery = $this->deliveries->register(
			$eventKey,
			$eventType,
			$file->getId(),
			$request->getId(),
			$file->getUserId(),
			$this->crypto->encrypt($email),
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

		// The database commits the claim before any external side effect.
		$outcome = NotificationDeliveryState::UNKNOWN;
		$reason = 'unexpected_error';
		try {
			$send();
			$outcome = NotificationDeliveryState::ACCEPTED;
			$reason = null;
		} catch (\Exception $e) {
			[$outcome, $reason] = $this->classify($e);
			$this->logger->warning('Mail notification did not receive confirmed transport acceptance', [
				'deliveryId' => $delivery->getId(),
				'outcome' => $outcome->value,
				'reasonCode' => $reason,
			]);
			throw $e;
		} finally {
			// Also records an unexpected PHP Error as UNKNOWN, without masking
			// the programming error or catching every Throwable for fallback.
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
		// A generic SMTP exception may occur after DATA was accepted.
		return [NotificationDeliveryState::UNKNOWN, 'transport_outcome_unknown'];
	}
}
