<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Events;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Enum\FileStatus;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IWebhookCompatibleEvent;

/** Public event for a workflow transitioning from draft to ready to sign. */
final class SigningRequestReadyEvent extends Event implements IWebhookCompatibleEvent {
	public const CONTRACT_VERSION = 1;

	private function __construct(
		private readonly string $requestUuid,
		private readonly string $requestType,
		private readonly string $eventId,
	) {
		parent::__construct();
	}

	public static function fromTransition(FileEntity $file, int $previousStatus): ?self {
		if ($file->hasParent()
			|| $previousStatus !== FileStatus::DRAFT->value
			|| $file->getStatus() !== FileStatus::ABLE_TO_SIGN->value
			|| $file->getUuid() === '') {
			return null;
		}

		$transitionId = ($file->getMetadata() ?? [])['ready_event_id'] ?? null;
		$eventId = 'libresign.workflow.ready.v1:' . $file->getUuid();
		if (is_string($transitionId) && $transitionId !== '') {
			$eventId .= ':' . $transitionId;
		}
		return new self($file->getUuid(), $file->isEnvelope() ? 'envelope' : 'document', $eventId);
	}

	#[\Override]
	public function getWebhookSerializable(): array {
		return [
			'contractVersion' => self::CONTRACT_VERSION,
			'eventId' => $this->eventId,
			'requestUuid' => $this->requestUuid,
			'requestType' => $this->requestType,
			'status' => 'ready_to_sign',
		];
	}
}
