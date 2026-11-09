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

final class SigningRequestCompletedEvent extends Event implements IWebhookCompatibleEvent {
	public const CONTRACT_VERSION = 1;

	private function __construct(
		private readonly string $requestUuid,
		private readonly string $requestType,
	) {
		parent::__construct();
	}

	public static function fromTransition(FileEntity $request, int $previousStatus): ?self {
		if ($previousStatus === FileStatus::SIGNED->value || $request->getStatus() !== FileStatus::SIGNED->value) {
			return null;
		}

		if ($request->getUuid() === '') {
			return null;
		}

		return new self($request->getUuid(), $request->isEnvelope() ? 'envelope' : 'document');
	}

	#[\Override]
	public function getWebhookSerializable(): array {
		return [
			'contractVersion' => self::CONTRACT_VERSION,
			'eventId' => 'libresign.workflow.completed.v1:' . $this->requestUuid,
			'requestUuid' => $this->requestUuid,
			'requestType' => $this->requestType,
			'status' => 'completed',
		];
	}
}
