<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Events;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\SignRequestStatus;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IWebhookCompatibleEvent;

/** One committed business rejection, independent of identification-method count. */
final class SignerRejectedWebhookEvent extends Event implements IWebhookCompatibleEvent {
	public const CONTRACT_VERSION = 1;

	private function __construct(
		private readonly string $requestUuid,
		private readonly string $requestType,
		private readonly string $documentUuid,
		private readonly int $signerId,
		private readonly bool $workflowCanceled,
		private readonly string $eventId,
	) {
		parent::__construct();
	}

	public static function afterRejection(FileEntity $root, FileEntity $document, SignRequest $signer, bool $workflowCanceled): ?self {
		if ($root->getUuid() === '' || $document->getUuid() === ''
			|| $signer->getId() === null
			|| $signer->getStatusEnum() !== SignRequestStatus::REJECTED
			|| $signer->getRejectedAt() === null) {
			return null;
		}

		$identity = implode(':', [$root->getUuid(), $document->getUuid(), $signer->getId(), $signer->getRejectedAt()->format('U.u')]);
		return new self(
			$root->getUuid(),
			$root->isEnvelope() ? 'envelope' : 'document',
			$document->getUuid(),
			$signer->getId(),
			$workflowCanceled,
			'libresign.signer.rejected.v1:' . hash('sha256', $identity),
		);
	}

	#[\Override]
	public function getWebhookSerializable(): array {
		return [
			'contractVersion' => self::CONTRACT_VERSION,
			'eventId' => $this->eventId,
			'requestUuid' => $this->requestUuid,
			'requestType' => $this->requestType,
			'documentUuid' => $this->documentUuid,
			'signerId' => $this->signerId,
			'status' => 'rejected',
			'workflowCanceled' => $this->workflowCanceled,
		];
	}
}
