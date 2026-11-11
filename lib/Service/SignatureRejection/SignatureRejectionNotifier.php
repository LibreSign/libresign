<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureRejection;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Events\SignatureRejectedEvent;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Service\Webhook\NativeWebhookEventPublisher;
use OCP\EventDispatcher\IEventDispatcher;
use Psr\Log\LoggerInterface;

class SignatureRejectionNotifier {
	public function __construct(
		private NativeWebhookEventPublisher $webhooks,
		private IdentifyMethodService $identifyMethodService,
		private IEventDispatcher $eventDispatcher,
		private SequentialSigningService $sequentialSigningService,
		private LoggerInterface $logger,
	) {
	}

	public function afterCommit(FileEntity $file, SignRequestEntity $signer, CommittedRejection $committed): void {
		$this->webhooks->signerRejected($file, $signer, $committed->previousRootStatus);
		$this->dispatchRejectedEvent($signer, $file, $file->getStatus() === FileStatus::CANCELED->value);
		$this->sequentialSigningService->notifyActivatedSigners($committed->activatedSigners);
	}

	private function dispatchRejectedEvent(
		SignRequestEntity $signRequest,
		FileEntity $libreSignFile,
		bool $workflowCanceled,
	): void {
		// The rejection is already committed at this point: a failing listener must
		// not turn a recorded rejection into an error for the signer.
		try {
			$groupedIdentifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
			foreach ($groupedIdentifyMethods as $identifyMethods) {
				foreach ($identifyMethods as $identifyMethod) {
					$this->eventDispatcher->dispatchTyped(new SignatureRejectedEvent(
						$signRequest,
						$libreSignFile,
						$identifyMethod,
						$workflowCanceled,
					));
				}
			}
		} catch (\Exception $e) {
			$this->logger->error('Error dispatching SignatureRejectedEvent', [
				'errorType' => get_debug_type($e),
			]);
		}
	}
}
