<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Webhook;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Events\NotificationDeliveryStatusChangedEvent;
use OCA\Libresign\Events\SignerRejectedWebhookEvent;
use OCA\Libresign\Events\SignerSignedWebhookEvent;
use OCA\Libresign\Events\SigningRequestCanceledEvent;
use OCA\Libresign\Events\SigningRequestCompletedEvent;
use OCA\Libresign\Events\SigningRequestReadyEvent;
use OCA\Libresign\Service\Webhook\NativeWebhookEventPublisher;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\IEventDispatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class NativeWebhookEventPublisherTest extends TestCase {
	private FileMapper&MockObject $files;
	private NotificationDeliveryMapper&MockObject $deliveries;
	private IEventDispatcher&MockObject $dispatcher;
	private LoggerInterface&MockObject $logger;
	private NativeWebhookEventPublisher $publisher;

	protected function setUp(): void {
		$this->files = $this->createMock(FileMapper::class);
		$this->deliveries = $this->createMock(NotificationDeliveryMapper::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->publisher = new NativeWebhookEventPublisher(
			$this->dispatcher, $this->logger, $this->files, $this->deliveries,
		);
	}

	private static function file(int $id, string $uuid, FileStatus $status): File {
		$file = new File();
		$file->setId($id);
		$file->setUuid($uuid);
		$file->setStatus($status->value);
		return $file;
	}

	private static function signer(bool $rejected = false): SignRequest {
		$signer = new SignRequest();
		$signer->setId(17);
		$signer->setUuid('SECRET_INVITATION');
		if ($rejected) {
			$signer->setStatusEnum(SignRequestStatus::REJECTED);
			$signer->setRejectedAt(new \DateTime('2026-11-10T10:00:00Z'));
			$signer->setRejectionComment('PRIVATE_REJECTION_COMMENT');
			$signer->setRejectionCommentPrivate(true);
		} else {
			$signer->setSigned(new \DateTime('2026-11-10T10:00:00Z'));
			$signer->setSignedHash('signed-hash');
		}
		return $signer;
	}

	private static function delivery(NotificationDeliveryState $state): NotificationDelivery {
		$delivery = new NotificationDelivery();
		$delivery->setId(9);
		$delivery->setFileId(25);
		$delivery->setState($state->value);
		$delivery->setAttemptCount(1);
		$delivery->setChannel('mail');
		$delivery->setRecipientEnc('PRIVATE_RECIPIENT');
		return $delivery;
	}

	public function testReadyEventIsPublishedForReadyRoot(): void {
		$root = self::file(13, 'request-uuid', FileStatus::ABLE_TO_SIGN);
		$this->dispatcher->expects($this->once())->method('dispatchTyped')
			->with($this->callback(static fn ($event): bool
				=> $event instanceof SigningRequestReadyEvent
				&& $event->getWebhookSerializable()['requestUuid'] === 'request-uuid'));
		$this->publisher->requestReady($root);
	}

	public function testReadyChildIsNotPublished(): void {
		$child = self::file(25, 'child-uuid', FileStatus::ABLE_TO_SIGN);
		$child->setParentFileId(13);
		$this->dispatcher->expects($this->never())->method('dispatchTyped');
		$this->publisher->requestReady($child);
	}

	public function testSignerSignedEventUsesEnvelopeRootAndNeverExposesInvitation(): void {
		$child = self::file(25, 'child-uuid', FileStatus::SIGNED);
		$child->setParentFileId(13);
		$root = self::file(13, 'root-uuid', FileStatus::PARTIAL_SIGNED);
		$root->setNodeType('envelope');
		$this->files->method('getById')->with(13)->willReturn($root);
		$this->dispatcher->expects($this->once())->method('dispatchTyped')
			->with($this->callback(static function ($event): bool {
				if (!$event instanceof SignerSignedWebhookEvent) {
					return false;
				}
				$payload = $event->getWebhookSerializable();
				return $payload['requestUuid'] === 'root-uuid'
					&& $payload['documentUuid'] === 'child-uuid'
					&& $payload['requestType'] === 'envelope'
					&& !str_contains(json_encode($payload), 'SECRET_INVITATION');
			}));
		$this->publisher->signerSigned($child, self::signer());
	}

	public function testFailedRejectionListenerDoesNotSuppressCancellation(): void {
		$root = self::file(13, 'root-uuid', FileStatus::CANCELED);
		$root->setNodeType('envelope');
		$child = self::file(25, 'child-uuid', FileStatus::CANCELED);
		$child->setParentFileId(13);
		$this->files->expects($this->never())->method('getById');
		$published = [];
		$this->dispatcher->method('dispatchTyped')->willReturnCallback(
			static function ($event) use (&$published): void {
				$published[] = $event;
				if ($event instanceof SignerRejectedWebhookEvent) {
					throw new \RuntimeException('PRIVATE_REJECTION_COMMENT');
				}
			});
		$this->logger->expects($this->once())->method('warning')
			->with('Native webhook listener failed after commit', $this->callback(
				static fn (array $context): bool
					=> ($context['phase'] ?? null) === 'dispatch'
					&& ($context['event'] ?? null) === 'signer.rejected'
					&& !str_contains(json_encode($context), 'PRIVATE_REJECTION_COMMENT'),
			));
		$this->publisher->signerRejected($root, $child, self::signer(true), FileStatus::ABLE_TO_SIGN->value);
		$this->assertCount(1, array_filter($published, static fn ($event): bool => $event instanceof SigningRequestCanceledEvent));
		$this->assertCount(1, array_filter($published, static fn ($event): bool => $event instanceof SignerRejectedWebhookEvent));
	}

	public function testRejectionCanCompletePreviouslyPartiallySignedWorkflow(): void {
		$root = self::file(13, 'root-uuid', FileStatus::SIGNED);
		$published = [];
		$this->dispatcher->method('dispatchTyped')->willReturnCallback(
			static function ($event) use (&$published): void {
				$published[] = $event;
			});
		$this->publisher->signerRejected($root, $root, self::signer(true), FileStatus::PARTIAL_SIGNED->value);
		$this->assertCount(1, array_filter($published, static fn ($event): bool => $event instanceof SigningRequestCompletedEvent));
		$this->assertCount(0, array_filter($published, static fn ($event): bool => $event instanceof SigningRequestCanceledEvent));
		$rejection = array_values(array_filter($published, static fn ($event): bool => $event instanceof SignerRejectedWebhookEvent))[0];
		$this->assertFalse($rejection->getWebhookSerializable()['workflowCanceled']);
		$this->assertStringNotContainsString('PRIVATE_REJECTION_COMMENT', json_encode($rejection->getWebhookSerializable()));
	}

	/** @return array<string, array{FileStatus, FileStatus, bool}> */
	public static function completionStates(): array {
		return [
			'just signed' => [FileStatus::PARTIAL_SIGNED, FileStatus::SIGNED, true],
			'already signed' => [FileStatus::SIGNED, FileStatus::SIGNED, false],
			'not complete' => [FileStatus::ABLE_TO_SIGN, FileStatus::PARTIAL_SIGNED, false],
		];
	}

	#[DataProvider('completionStates')]
	public function testCompletionUsesPersistedStatus(FileStatus $before, FileStatus $after, bool $shouldPublish): void {
		$root = self::file(13, 'request-uuid', $before);
		$this->files->method('getById')->with(13)->willReturn(self::file(13, 'request-uuid', $after));
		$this->dispatcher->expects($shouldPublish ? $this->once() : $this->never())
			->method('dispatchTyped');
		$this->publisher->requestCompleted($root, $before->value);
	}

	public function testUnpersistedRequestDoesNotPublishCompletion(): void {
		$request = new File();
		$request->setUuid('uuid');
		$request->setStatus(FileStatus::SIGNED->value);
		$this->dispatcher->expects($this->never())->method('dispatchTyped');
		$this->publisher->requestCompleted($request, FileStatus::PARTIAL_SIGNED->value);
	}

	public function testDeliveryEventUsesEnvelopeRootAndHidesRecipient(): void {
		$this->deliveries->method('findById')->with(9)->willReturn(self::delivery(NotificationDeliveryState::ACCEPTED));
		$child = self::file(25, 'child', FileStatus::ABLE_TO_SIGN);
		$child->setParentFileId(13);
		$root = self::file(13, 'root-uuid', FileStatus::ABLE_TO_SIGN);
		$root->setNodeType('envelope');
		$this->files->method('getById')->willReturnMap([[25, $child], [13, $root]]);
		$this->dispatcher->expects($this->once())->method('dispatchTyped')
			->with($this->callback(static function ($event): bool {
				if (!$event instanceof NotificationDeliveryStatusChangedEvent) {
					return false;
				}
				$payload = $event->getWebhookSerializable();
				return $payload['requestUuid'] === 'root-uuid'
					&& $payload['state'] === NotificationDeliveryState::ACCEPTED->value
					&& !str_contains(json_encode($payload), 'PRIVATE_RECIPIENT');
			}));
		$this->publisher->notificationOutcome(9);
	}

	public function testPendingDeliveryCannotPublishOutcome(): void {
		$this->deliveries->method('findById')->willReturn(self::delivery(NotificationDeliveryState::PENDING));
		$this->files->method('getById')->willReturn(self::file(13, 'uuid', FileStatus::ABLE_TO_SIGN));
		$this->dispatcher->expects($this->never())->method('dispatchTyped');
		$this->publisher->notificationOutcome(9);
	}

	public function testMissingPersistedDeliveryIsLoggedAsAnErrorWithoutPrivateMessage(): void {
		$this->deliveries->method('findById')->willThrowException(
			new DoesNotExistException('PRIVATE_RECIPIENT'));
		$this->dispatcher->expects($this->never())->method('dispatchTyped');
		$this->logger->expects($this->once())->method('error')
			->with('Native webhook publication failed after commit', $this->callback(
				static fn (array $context): bool
					=> ($context['phase'] ?? null) === 'prepare'
					&& ($context['deliveryId'] ?? null) === 9
					&& ($context['errorType'] ?? null) === DoesNotExistException::class
					&& is_string($context['errorFile'] ?? null)
					&& is_int($context['errorLine'] ?? null)
					&& !str_contains(json_encode($context), 'PRIVATE_RECIPIENT'),
			));
		$this->publisher->notificationOutcome(9);
	}

	/** @return array<string, array{\Throwable}> */
	public static function unexpectedPreparationErrors(): array {
		return [
			'programming error' => [new \TypeError('INTERNAL_DETAILS')],
			'unexpected database failure' => [new \RuntimeException('INTERNAL_DETAILS')],
		];
	}

	#[DataProvider('unexpectedPreparationErrors')]
	public function testUnexpectedPreparationErrorsRemainVisible(\Throwable $failure): void {
		$this->deliveries->method('findById')->willThrowException($failure);
		$this->dispatcher->expects($this->never())->method('dispatchTyped');
		$this->expectException($failure::class);
		$this->publisher->notificationOutcome(9);
	}

	public function testListenerProgrammingErrorIsLoggedAsErrorAndCannotUndoCommittedState(): void {
		$root = self::file(13, 'ready-uuid', FileStatus::ABLE_TO_SIGN);
		$this->dispatcher->method('dispatchTyped')->willThrowException(
			new \TypeError('PRIVATE_WEBHOOK_DETAILS'));
		$this->logger->expects($this->once())->method('error')
			->with('Native webhook publication failed after commit', $this->callback(
				static fn (array $context): bool
					=> ($context['phase'] ?? null) === 'dispatch'
					&& ($context['event'] ?? null) === 'request.ready'
					&& ($context['errorType'] ?? null) === \TypeError::class
					&& !str_contains(json_encode($context), 'PRIVATE_WEBHOOK_DETAILS'),
			));
		$this->publisher->requestReady($root);
		$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $root->getStatus());
	}
}
