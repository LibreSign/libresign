<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\IdDocs;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\IdDocRejectionNotifier;
use OCA\Libresign\Service\IdDocRejectionRecipientResolver;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\Notification\IdDocRejectionPayload;
use OCA\Libresign\Service\Notification\InAppChannel;
use OCA\Libresign\Service\Notification\InAppPayload;
use OCA\Libresign\Service\Notification\MailChannel;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\NotificationDeliveryService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\Attributes\DataProvider;

final class IdDocRejectionNotifierTest extends TestCase {
	/**
	 * @param list<array{uid: ?string, email: ?string}> $recipients
	 * @param list<array{channel: string, recipient: string, accountUid: ?string}> $expected
	 */
	#[DataProvider('deliveries')]
	public function testQueueProducesRecipientChannelMessages(
		array $recipients,
		array $expected,
	): void {
		$resolver = $this->createMock(IdDocRejectionRecipientResolver::class);
		$resolver->expects($this->once())->method('resolve')
			->with(null, 42)->willReturn($recipients);
		$request = new SignRequest();
		$request->setId(42);
		$requests = $this->createMock(SignRequestMapper::class);
		$requests->method('getById')->with(42)->willReturn($request);
		$manager = $this->createMock(IManager::class);
		$notification = $this->createMock(INotification::class);
		foreach (['setApp', 'setUser', 'setObject', 'setDateTime'] as $setter) {
			$notification->method($setter)->willReturnSelf();
		}
		$subjects = [];
		$notification->method('setSubject')->willReturnCallback(
			static function (string $subject, array $parameters = []) use (&$subjects, $notification): INotification {
				$subjects[] = [$subject, $parameters];
				return $notification;
			},
		);
		$manager->method('createNotification')->willReturn($notification);

		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getDateTime')->willReturn(new \DateTime('2026-11-10T12:00:00Z'));
		$delivery = $this->createMock(NotificationDeliveryService::class);
		$messages = [];
		$delivery->method('dispatch')->willReturnCallback(static function (NotificationMessage $message) use (&$messages): bool {
			$messages[] = $message;
			return true;
		});
		$notifier = new IdDocRejectionNotifier(
			$resolver, $requests, $clock, $delivery,
			new MailChannel($this->createMock(MailService::class)),
			new InAppChannel($manager), $manager,
		);
		$doc = new IdDocs();
		$doc->setId(7);
		$doc->setSignRequestId(42);
		$file = new File();
		$file->setId(9);
		$file->setName('Identity document.pdf');
		$notifier->queue($doc, $file, 'Photo is blurry');

		$actual = [];
		foreach ($messages as $message) {
			$this->assertSame('id_doc_rejected', $message->eventType);
			$this->assertSame('id-doc-rejected:7', $message->sourceEventId);
			$this->assertSame($file, $message->file);
			$this->assertSame($request, $message->signRequest);
			if ($message->channel === 'mail') {
				$this->assertInstanceOf(IdDocRejectionPayload::class, $message->payload);
				$this->assertSame('Photo is blurry', $message->payload->reason);
				$this->assertSame($message->payload->recipientUserId !== null, $message->payload->accountSigner);
				$accountUid = $message->payload->recipientUserId;
			} else {
				$this->assertInstanceOf(InAppPayload::class, $message->payload);
				$accountUid = $message->recipient;
			}
			$actual[] = ['channel' => $message->channel, 'recipient' => $message->recipient, 'accountUid' => $accountUid];
		}
		$normalize = static function (array $entries): array {
			usort($entries, static fn (array $a, array $b): int => strcmp(json_encode($a), json_encode($b)));
			return $entries;
		};
		$this->assertSame($normalize($expected), $normalize($actual));
		$this->assertCount(count(array_filter($expected, static fn (array $entry): bool => $entry['channel'] === 'nextcloud')), $subjects);
		foreach ($subjects as [$subject, $parameters]) {
			$this->assertSame('identification_document_rejected', $subject);
			$this->assertSame(['reason' => 'Photo is blurry', 'documentName' => 'Identity document.pdf'], $parameters);
		}
	}

	public static function deliveries(): array {
		return [
			'external signer by email' => [
				[['uid' => null, 'email' => 'external@example.test']],
				[['channel' => 'mail', 'recipient' => 'external@example.test', 'accountUid' => null]],
			],
			'account signer gets email and Nextcloud notification' => [
				[['uid' => 'signer', 'email' => 'signer@example.test']],
				[
					['channel' => 'mail', 'recipient' => 'signer@example.test', 'accountUid' => 'signer'],
					['channel' => 'nextcloud', 'recipient' => 'signer', 'accountUid' => 'signer'],
				],
			],
			'account signer without email' => [
				[['uid' => 'signer', 'email' => null]],
				[['channel' => 'nextcloud', 'recipient' => 'signer', 'accountUid' => 'signer']],
			],
			'guest invalid email produces no delivery' => [[['uid' => null, 'email' => 'bad-email']], []],
			'account invalid email retains in-app notification' => [
				[['uid' => 'signer', 'email' => 'bad-email']],
				[['channel' => 'nextcloud', 'recipient' => 'signer', 'accountUid' => 'signer']],
			],
			'mixed recipients keep their original identities' => [
				[
					['uid' => null, 'email' => 'guest@example.test'],
					['uid' => 'signer', 'email' => 'signer@example.test'],
				],
				[
					['channel' => 'mail', 'recipient' => 'guest@example.test', 'accountUid' => null],
					['channel' => 'mail', 'recipient' => 'signer@example.test', 'accountUid' => 'signer'],
					['channel' => 'nextcloud', 'recipient' => 'signer', 'accountUid' => 'signer'],
				],
			],
			'duplicate recipients rely on outbox idempotency' => [
				[
					['uid' => null, 'email' => 'same@example.test'],
					['uid' => null, 'email' => 'same@example.test'],
				],
				[
					['channel' => 'mail', 'recipient' => 'same@example.test', 'accountUid' => null],
					['channel' => 'mail', 'recipient' => 'same@example.test', 'accountUid' => null],
				],
			],
			'empty recipients' => [[], []],
		];
	}

	public function testNoSignRequestProducesNoNotification(): void {
		$resolver = $this->createMock(IdDocRejectionRecipientResolver::class);
		$resolver->expects($this->never())->method('resolve');
		$requests = $this->createMock(SignRequestMapper::class);
		$requests->expects($this->never())->method('getById');
		$delivery = $this->createMock(NotificationDeliveryService::class);
		$delivery->expects($this->never())->method('dispatch');
		$manager = $this->createMock(IManager::class);
		$notifier = new IdDocRejectionNotifier(
			$resolver, $requests, $this->createMock(ITimeFactory::class), $delivery,
			new MailChannel($this->createMock(MailService::class)),
			new InAppChannel($manager), $manager,
		);
		$doc = new IdDocs();
		$doc->setSignRequestId(null);
		$notifier->queue($doc, new File(), 'Rejected');
	}

	public function testFlushCommittedUsesExistingOutbox(): void {
		$delivery = $this->createMock(NotificationDeliveryService::class);
		$delivery->expects($this->once())->method('flushCommitted');
		$manager = $this->createMock(IManager::class);
		$notifier = new IdDocRejectionNotifier(
			$this->createMock(IdDocRejectionRecipientResolver::class),
			$this->createMock(SignRequestMapper::class),
			$this->createMock(ITimeFactory::class), $delivery,
			new MailChannel($this->createMock(MailService::class)), new InAppChannel($manager), $manager,
		);
		$notifier->flushCommitted();
	}
}
