<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Events;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Events\NotificationDeliveryStatusChangedEvent;
use OCA\Libresign\Events\SignerRejectedWebhookEvent;
use OCA\Libresign\Events\SignerSignedWebhookEvent;
use OCA\Libresign\Events\SigningRequestCanceledEvent;
use OCA\Libresign\Events\SigningRequestReadyEvent;
use OCP\EventDispatcher\IWebhookCompatibleEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NativeWebhookEventsTest extends TestCase {
	private static function file(string $uuid = 'workflow-uuid', bool $envelope = false): File {
		$file = new File();
		$file->setUuid($uuid);
		$file->setNodeType($envelope ? 'envelope' : 'file');
		return $file;
	}

	/** @return array<string, array{FileStatus, FileStatus, bool, bool}> */
	public static function readyTransitions(): array {
		return [
			'draft to ready' => [FileStatus::DRAFT, FileStatus::ABLE_TO_SIGN, false, true],
			'repeated ready' => [FileStatus::ABLE_TO_SIGN, FileStatus::ABLE_TO_SIGN, false, false],
			'draft to signed' => [FileStatus::DRAFT, FileStatus::SIGNED, false, false],
			'child draft to ready' => [FileStatus::DRAFT, FileStatus::ABLE_TO_SIGN, true, false],
		];
	}

	#[DataProvider('readyTransitions')]
	public function testReadyRequiresRealRootTransition(FileStatus $previous, FileStatus $next, bool $child, bool $emitted): void {
		$file = self::file();
		$file->setStatus($next->value);
		if ($child) {
			$file->setParentFileId(42);
		}
		$event = SigningRequestReadyEvent::fromTransition($file, $previous->value);
		if (!$emitted) {
			self::assertNull($event);
			return;
		}
		self::assertInstanceOf(IWebhookCompatibleEvent::class, $event);
		self::assertSame([
			'contractVersion' => 1,
			'eventId' => 'libresign.workflow.ready.v1:workflow-uuid',
			'requestUuid' => 'workflow-uuid',
			'requestType' => 'document',
			'status' => 'ready_to_sign',
		], $event->getWebhookSerializable());
	}

	/** @return array<string, array{FileStatus, FileStatus, bool, bool}> */
	public static function canceledTransitions(): array {
		return [
			'ready to canceled' => [FileStatus::ABLE_TO_SIGN, FileStatus::CANCELED, false, true],
			'already canceled' => [FileStatus::CANCELED, FileStatus::CANCELED, false, false],
			'not canceled' => [FileStatus::ABLE_TO_SIGN, FileStatus::PARTIAL_SIGNED, false, false],
			'canceled envelope child' => [FileStatus::ABLE_TO_SIGN, FileStatus::CANCELED, true, false],
		];
	}

	#[DataProvider('canceledTransitions')]
	public function testCanceledRequiresAggregateTransition(FileStatus $previous, FileStatus $next, bool $child, bool $emitted): void {
		$file = self::file('envelope-uuid', true);
		$file->setStatus($next->value);
		if ($child) {
			$file->setParentFileId(42);
		}
		$event = SigningRequestCanceledEvent::fromTransition($file, $previous->value);
		if (!$emitted) {
			self::assertNull($event);
			return;
		}
		self::assertInstanceOf(IWebhookCompatibleEvent::class, $event);
		self::assertSame([
			'contractVersion' => 1,
			'eventId' => 'libresign.workflow.canceled.v1:envelope-uuid',
			'requestUuid' => 'envelope-uuid',
			'requestType' => 'envelope',
			'status' => 'canceled',
		], $event->getWebhookSerializable());
	}

	public function testSignerSignedHasStableNonSecretCorrelation(): void {
		$root = self::file('envelope-uuid', true);
		$document = self::file('child-uuid');
		$document->setParentFileId(42);
		$signer = new SignRequest();
		$signer->setId(52);
		$signer->setUuid('SECRET-INVITATION-UUID');
		$signer->setSigned(new \DateTime('2026-11-09T11:00:00Z'));
		$signer->setSignedHash('content-hash');
		$event = SignerSignedWebhookEvent::afterSigning($root, $document, $signer);
		self::assertInstanceOf(IWebhookCompatibleEvent::class, $event);
		$payload = $event->getWebhookSerializable();
		self::assertSame('envelope', $payload['requestType']);
		self::assertSame('envelope-uuid', $payload['requestUuid']);
		self::assertSame('child-uuid', $payload['documentUuid']);
		self::assertSame(52, $payload['signerId']);
		self::assertSame('signed', $payload['status']);
		self::assertSame($payload, SignerSignedWebhookEvent::afterSigning($root, $document, $signer)->getWebhookSerializable());
		self::assertStringNotContainsString('SECRET-INVITATION-UUID', json_encode($payload));
		$signer->setSignedHash(null);
		self::assertNull(SignerSignedWebhookEvent::afterSigning($root, $document, $signer));
	}

	public function testRejectedEventDoesNotDiscloseCommentsOrInvitation(): void {
		$root = self::file('workflow-uuid');
		$signer = new SignRequest();
		$signer->setId(9);
		$signer->setUuid('SECRET-INVITATION');
		$signer->setRejectedAt(new \DateTime('2026-11-09T11:00:00Z'));
		$signer->setStatusEnum(SignRequestStatus::REJECTED);
		$signer->setRejectionComment('PRIVATE CASE DETAILS');
		$signer->setRejectionCommentPrivate(true);
		$event = SignerRejectedWebhookEvent::afterRejection($root, $root, $signer, true);
		self::assertInstanceOf(IWebhookCompatibleEvent::class, $event);
		$p = $event->getWebhookSerializable();
		self::assertSame(true, $p['workflowCanceled']);
		self::assertSame('rejected', $p['status']);
		self::assertSame(9, $p['signerId']);
		self::assertSame($p, SignerRejectedWebhookEvent::afterRejection($root, $root, $signer, true)->getWebhookSerializable());
		self::assertStringNotContainsString('PRIVATE CASE DETAILS', json_encode($p));
		self::assertStringNotContainsString('SECRET-INVITATION', json_encode($p));
		$signer->setStatusEnum(SignRequestStatus::SIGNED);
		self::assertNull(SignerRejectedWebhookEvent::afterRejection($root, $root, $signer, false));
	}

	/** @return array<string, array{NotificationDeliveryState, int, bool}> */
	public static function persistedDeliveryOutcomes(): array {
		return [
			'pending' => [NotificationDeliveryState::PENDING, 0, false],
			'claimed' => [NotificationDeliveryState::SENDING, 1, false],
			'accepted by adapter' => [NotificationDeliveryState::ACCEPTED, 1, true],
			'unknown result' => [NotificationDeliveryState::UNKNOWN, 2, true],
			'failed' => [NotificationDeliveryState::FAILED, 2, true],
			'temporary failure' => [NotificationDeliveryState::TEMPORARY_FAILURE, 2, true],
		];
	}

	#[DataProvider('persistedDeliveryOutcomes')]
	public function testDeliveryReportsOnlyPersistedAttemptOutcomes(NotificationDeliveryState $state, int $attempt, bool $emitted): void {
		$delivery = new NotificationDelivery();
		$delivery->setId(15);
		$delivery->setEventKey(str_repeat('a', 64));
		$delivery->setRecipientEnc('ENCRYPTED_RECIPIENT');
		$delivery->setChannel('mail');
		$delivery->setState($state->value);
		$delivery->setAttemptCount($attempt);
		$event = NotificationDeliveryStatusChangedEvent::fromPersistedDelivery(self::file(), $delivery);
		if (!$emitted) {
			self::assertNull($event);
			return;
		}
		self::assertInstanceOf(IWebhookCompatibleEvent::class, $event);
		$p = $event->getWebhookSerializable();
		self::assertSame($state->value, $p['state']);
		self::assertSame($attempt, $p['attempt']);
		self::assertSame(15, $p['deliveryId']);
		self::assertSame('mail', $p['channel']);
		self::assertStringNotContainsString('ENCRYPTED_RECIPIENT', json_encode($p));
	}
}
