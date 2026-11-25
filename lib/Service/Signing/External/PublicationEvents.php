<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\External;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Events\SignedEventFactory;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Service\Webhook\NativeWebhookEventPublisher;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File as NextcloudFile;
use Psr\Log\LoggerInterface;

/** Best-effort side effects, never executed before the signing transaction commits. */
final class PublicationEvents {
	public function __construct(
		private FileStatusService $fileStatuses,
		private SignRequestMapper $signRequests,
		private SequentialSigningService $sequential,
		private SignedEventFactory $signedEventFactory,
		private IEventDispatcher $dispatcher,
		private NativeWebhookEventPublisher $webhooks,
		private LoggerInterface $logger,
	) {
	}

	/** @param list<SignRequest> $activated */
	public function dispatch(
		FileEntity $file,
		SignRequest $request,
		NextcloudFile $output,
		int $previousStatus,
		array $activated,
	): void {
		// A failure of one channel must not suppress all other channels.
		$steps = [
			'file-status-cache' => fn () => $this->fileStatuses->onQueuedJobCommitted($file),
			'signer-cache' => fn () => $this->signRequests->invalidateEntityCache($request),
			'next-signers' => fn () => $this->sequential->notifyActivatedSigners($activated),
			'signed-event' => fn () => $this->dispatcher->dispatchTyped(
				$this->signedEventFactory->make($request, $file, $output),
			),
			'signer-webhook' => fn () => $this->webhooks->signerSigned($file, $request),
			'request-webhook' => fn () => $this->webhooks->requestCompleted($file, $previousStatus),
		];
		foreach ($steps as $step => $action) {
			try {
				$action();
			} catch (\Throwable $e) {
				// Exceptions may contain signer tokens or remote credentials.
				$this->logger->error('CSC post-commit action failed', [
					'step' => $step, 'exceptionClass' => $e::class, 'signRequestId' => $request->getId(),
				]);
			}
		}
	}
}
