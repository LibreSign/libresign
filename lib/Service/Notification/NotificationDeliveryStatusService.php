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
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCA\Libresign\Service\Validation\SigningRequestValidator;
use OCP\IUserSession;

/**
 * Read the state of one manual notification event.
 *
 * No recipient, message body, event key or provider error is returned.
 * Authorization is checked before any delivery record is read.
 */
final class NotificationDeliveryStatusService {
	public function __construct(
		private SigningRequestValidator $validator,
		private FileInputValidator $fileInputValidator,
		private IUserSession $userSession,
		private SignRequestMapper $signRequests,
		private NotificationDeliveryMapper $deliveries,
		private NotificationDeliveryKeyFactory $keys,
	) {
	}

	/** @return array{status: string, complete: bool, channels: list<array{channel: string, state: string}>} */
	public function status(int $fileId, int $signRequestId, string $requestId): array {
		$this->validator->canRequestSign($this->userSession->getUser());
		$this->fileInputValidator->validateLibreSignFileId($fileId);
		$this->validator->iRequestedSignThisFile($this->userSession->getUser(), $fileId);
		$this->signRequests->getByFileIdAndSignRequestId($fileId, $signRequestId);
		if (!preg_match('/\A[a-f0-9]{32}\z/D', $requestId)) {
			throw new LibresignException('Invalid notification request ID');
		}

		$correlationId = $this->keys->correlationId('manual:' . $requestId, 'sign_request', $fileId, $signRequestId);
		$channels = [];
		$processing = false;
		$attention = false;
		foreach ($this->deliveries->findForEvent($correlationId) as $delivery) {
			if ($delivery->getFileId() !== $fileId
				|| $delivery->getSignRequestId() !== $signRequestId
				|| $delivery->getEventType() !== 'sign_request') {
				continue;
			}
			$state = $delivery->getState();
			$channels[] = ['channel' => $delivery->getChannel(), 'state' => $state];
			if (in_array($state, [NotificationDeliveryState::PENDING->value, NotificationDeliveryState::SENDING->value], true)) {
				$processing = true;
			} elseif ($state !== NotificationDeliveryState::ACCEPTED->value) {
				$attention = true;
			}
		}

		return [
			'status' => $processing ? 'processing' : ($attention ? 'attention_required' : ($channels === [] ? 'not_found' : 'completed')),
			'complete' => !$processing,
			'channels' => $channels,
		];
	}
}
