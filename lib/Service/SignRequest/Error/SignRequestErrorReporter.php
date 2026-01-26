<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignRequest\Error;

use OCA\Libresign\Service\SignRequest\ProgressService;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class SignRequestErrorReporter extends AbstractLogger {
	public function __construct(
		private ProgressService $progressService,
		private LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function log($level, $message, array $context = []): void {
		$this->maybeStoreProgressError($level, $context);
		$this->logger->log($level, $message, $context);
	}

	private function maybeStoreProgressError(string $level, array $context): void {
		if ($level !== LogLevel::ERROR) {
			return;
		}

		$exception = $context['exception'] ?? null;
		if (!$exception instanceof \Throwable) {
			return;
		}

		$signRequestUuid = $context['signRequestUuid'] ?? null;
		if (empty($signRequestUuid)) {
			return;
		}

		$ttl = (int)($context['ttl'] ?? ProgressService::ERROR_CACHE_TTL);
		$fileId = $context['fileId'] ?? null;
		$signRequestId = $context['signRequestId'] ?? null;
		$payload = ErrorPayloadBuilder::fromException(
			e: $exception,
			fileId: is_numeric($fileId) ? (int)$fileId : null,
			signRequestId: is_numeric($signRequestId) ? (int)$signRequestId : null,
			signRequestUuid: $signRequestUuid
		)->build();

		$this->progressService->setSignRequestError($signRequestUuid, $payload, $ttl);
		if (is_numeric($fileId)) {
			$this->progressService->setFileError($signRequestUuid, (int)$fileId, $payload, $ttl);
		}
	}
}
