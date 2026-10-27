<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Events;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\EventDispatcher\Event;

class SendSignNotificationEvent extends Event {
	public const FILE_TO_SIGN = 'libresign_file_to_sign';
	public function __construct(
		private SignRequest $signRequest,
		private FileEntity $libreSignFile,
		private IIdentifyMethod $identifyMethod,
		private ?string $deliveryEventId = null,
	) {
		// Re-dispatching the same persisted business state is not a new
		// delivery. Reminder invocations pass a period-specific event ID.
		// Metadata is mutable and often includes unrelated channel activity;
		// it must never change the identity of the same logical mail event.
		// Explicit manual notices and periodic reminders supply their own IDs.
		if ($this->deliveryEventId === null) {
			$this->deliveryEventId = hash('sha256', json_encode([
				'file' => $this->libreSignFile->getUuid(),
				'request' => $this->signRequest->getUuid(),
				'requestId' => $this->signRequest->getId(),
				'role' => $this->signRequest->getParticipantRole(),
				'status' => $this->signRequest->getStatus(),
				'order' => $this->signRequest->getSigningOrder(),
				'name' => $this->signRequest->getDisplayName(),
				'description' => $this->signRequest->getDescription(),
			], JSON_THROW_ON_ERROR));
		}
	}

	public function getDeliveryEventId(): string {
		return $this->deliveryEventId;
	}

	public function getLibreSignFile(): FileEntity {
		return $this->libreSignFile;
	}

	public function getSignRequest(): SignRequest {
		return $this->signRequest;
	}

	public function getIdentifyMethod(): IIdentifyMethod {
		return $this->identifyMethod;
	}
}
