<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Events;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IWebhookCompatibleEvent;

/**
 * An individual document-level signer action, including envelope children.
 * The secret signing invitation UUID is never exposed.
 */
final class SignerSignedWebhookEvent extends Event implements IWebhookCompatibleEvent {
	public const CONTRACT_VERSION = 1;

	private function __construct(
		private readonly string $requestUuid,
		private readonly string $requestType,
		private readonly string $documentUuid,
		private readonly int $signerId,
		private readonly string $eventId,
	) {
		parent::__construct();
	}

	public static function afterSigning(FileEntity $root, FileEntity $document, SignRequest $signer): ?self {
		if ($root->getUuid() === '' || $document->getUuid() === ''
			|| $signer->getId() === null || $signer->getSigned() === null
			|| !is_string($signer->getSignedHash()) || $signer->getSignedHash() === '') {
			return null;
		}

		$identity = implode(':', [$root->getUuid(), $document->getUuid(), $signer->getId(), $signer->getSignedHash()]);
		return new self(
			$root->getUuid(),
			$root->isEnvelope() ? 'envelope' : 'document',
			$document->getUuid(),
			$signer->getId(),
			'libresign.signer.signed.v1:' . hash('sha256', $identity),
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
			'status' => 'signed',
		];
	}
}
