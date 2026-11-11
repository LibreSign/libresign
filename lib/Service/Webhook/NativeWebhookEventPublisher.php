<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Webhook;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Events\NotificationDeliveryStatusChangedEvent;
use OCA\Libresign\Events\SignerRejectedWebhookEvent;
use OCA\Libresign\Events\SignerSignedWebhookEvent;
use OCA\Libresign\Events\SigningRequestCanceledEvent;
use OCA\Libresign\Events\SigningRequestCompletedEvent;
use OCA\Libresign\Events\SigningRequestReadyEvent;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use Psr\Log\LoggerInterface;

/**
 * Publishes native lifecycle events after business state has committed.
 *
 * Listener failures cannot undo committed operations. Event dispatch is
 * best-effort and LibreSign does not provide a replayable event outbox.
 */
class NativeWebhookEventPublisher {
	public function __construct(
		private IEventDispatcher $dispatcher,
		private LoggerInterface $logger,
		private FileMapper $files,
		private NotificationDeliveryMapper $deliveries,
	) {
	}

	public function requestReady(FileEntity $request): void {
		$this->publish('request.ready', ['fileId' => $request->getId()],
			static fn (): ?Event => SigningRequestReadyEvent::fromTransition($request, FileStatus::DRAFT->value));
	}

	public function signerSigned(FileEntity $document, SignRequestEntity $signer): void {
		$this->publish('signer.signed', [
			'fileId' => $document->getId(),
			'signRequestId' => $signer->getId(),
		], fn (): ?Event => SignerSignedWebhookEvent::afterSigning(
			$this->rootOf($document), $document, $signer,
		));
	}

	public function signerRejected(FileEntity $document, SignRequestEntity $signer, int $previousRootStatus): void {
		$context = ['fileId' => $document->getId(), 'signRequestId' => $signer->getId()];
		try {
			$root = $this->rootOf($document);
		} catch (DoesNotExistException $e) {
			$this->reportFailure($e, 'prepare', 'signer.rejected', $context);
			return;
		}

		$this->publish('signer.rejected', $context,
			static fn (): ?Event => SignerRejectedWebhookEvent::afterRejection(
				$root, $document, $signer, $root->getStatus() === FileStatus::CANCELED->value,
			));
		$this->publish('request.canceled', $context,
			static fn (): ?Event => SigningRequestCanceledEvent::fromTransition($root, $previousRootStatus));
		$this->publish('request.completed', $context,
			static fn (): ?Event => SigningRequestCompletedEvent::fromTransition($root, $previousRootStatus));
	}

	public function requestCompleted(FileEntity $request, int $previousStatus): void {
		$this->publish('request.completed', ['fileId' => $request->getId()],
			function () use ($request, $previousStatus): ?Event {
				$id = $request->getId();
				if ($id === null) {
					return null;
				}
				return SigningRequestCompletedEvent::fromTransition($this->files->getById($id), $previousStatus);
			});
	}

	public function notificationOutcome(int $deliveryId): void {
		$this->publish('notification.outcome', ['deliveryId' => $deliveryId],
			function () use ($deliveryId): ?Event {
				$delivery = $this->deliveries->findById($deliveryId);
				$document = $this->files->getById($delivery->getFileId());
				return NotificationDeliveryStatusChangedEvent::fromPersistedDelivery(
					$this->rootOf($document), $delivery,
				);
			});
	}

	/** @param \Closure(): ?Event $eventFactory */
	private function publish(string $eventName, array $context, \Closure $eventFactory): void {
		try {
			$event = $eventFactory();
		} catch (DoesNotExistException $e) {
			$this->reportFailure($e, 'prepare', $eventName, $context);
			return;
		}
		if ($event === null) {
			return;
		}

		try {
			$this->dispatcher->dispatchTyped($event);
		} catch (\Throwable $e) {
			$this->reportFailure($e, 'dispatch', $eventName, $context);
		}
	}

	private function rootOf(FileEntity $document): FileEntity {
		return $document->hasParent()
			? $this->files->getById($document->getParentFileId())
			: $document;
	}

	private function reportFailure(\Throwable $error, string $phase, string $eventName, array $context): void {
		$details = $context + [
			'event' => $eventName,
			'phase' => $phase,
			'errorType' => get_debug_type($error),
			'errorFile' => $error->getFile(),
			'errorLine' => $error->getLine(),
		];
		if ($phase === 'prepare' || $error instanceof \Error) {
			$this->logger->error('Native webhook publication failed after commit', $details);
		} else {
			$this->logger->warning('Native webhook listener failed after commit', $details);
		}
	}
}
