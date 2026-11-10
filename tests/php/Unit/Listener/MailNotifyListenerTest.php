<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Listener;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\IdentifyMethod as IdentifyMethodEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Events\SignRequestCanceledEvent;
use OCA\Libresign\Listener\MailNotifyListener;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\NotificationPreferenceResolver;
use OCP\Files\File;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MailNotifyListenerTest extends TestCase {
	private IUserManager&MockObject $userManager;
	private NotificationPreferenceResolver&MockObject $notificationPreferenceResolver;
	private MailNotifyListener $listener;

	protected function setUp(): void {
		$this->userManager = $this->createMock(IUserManager::class);
		$this->notificationPreferenceResolver = $this->createMock(NotificationPreferenceResolver::class);
		$userSession = $this->createMock(IUserSession::class);
		$identifyService = $this->createMock(IdentifyService::class);
		$signRequestMapper = $this->createMock(SignRequestMapper::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->listener = new MailNotifyListener(
			$this->userManager,
			$identifyService,
			$this->mailService,
			$signRequestMapper,
			$logger,
			$this->notificationPreferenceResolver,
		);
	}

	public function testEmailSignerWithExistingAccountReceivesInvitation(): void {
		$account = $this->account('0F1E2D3C4B5A69788796A5B4C3D2E1F0', 'bob@example.org');
		$this->userManager->method('getByEmail')->willReturn([$account]);
		$this->preferences->method('isEmailNotificationDisabled')->willReturn(true);

		$this->listener->handle($this->invitation('email', 'bob@example.org'));

		$message = $this->singleDelivery();
		$this->assertSame('bob@example.org', $message->recipient);
		$this->assertSame('sign_request', $message->eventType);
		$this->assertSame('mail', $message->channel);
		$this->assertSame('mail', $message->historyMethod);
		$payload = $message->payload;
		$this->assertInstanceOf(MailPayload::class, $payload);
		$this->assertTrue($payload->firstNotification);
	}

	public function testExternalEmailSignerReceivesInvitation(): void {
		$this->listener->handle($this->invitation('email', 'external@example.org'));

		$this->assertSame('external@example.org', $this->singleDelivery()->recipient);
	}

	public function testEmailObserverReceivesInvitation(): void {
		$request = $this->request();
		$request->setParticipantRole('observer');

		$this->listener->handle($this->invitation('email', 'observer@example.org', $request));

		$message = $this->singleDelivery();
		$this->assertSame('observer@example.org', $message->recipient);
		$this->assertSame($request, $message->signRequest);
	}

	public function testReminderKeepsEmailDestinationAndUsesUpdatedMailTemplate(): void {
		$this->persistedRequest->setMetadata(['notify' => [['method' => 'mail', 'date' => 42]]]);

		$this->listener->handle($this->invitation('email', 'bob@example.org'));

		$message = $this->singleDelivery();
		$this->assertSame('bob@example.org', $message->recipient);
		$payload = $message->payload;
		$this->assertInstanceOf(MailPayload::class, $payload);
		$this->assertFalse($payload->firstNotification);
	}

	public static function accountPreferences(): array {
		return [
			'email notifications enabled' => [false, true],
			'email notifications disabled' => [true, false],
		];
	}

	#[DataProvider('accountPreferences')]
	public function testAccountSignerRespectsMailPreference(bool $disabled, bool $shouldDeliver): void {
		$account = $this->account('bob-uid', 'bob@example.org');
		$this->userManager
			->method('get')
			->willReturn($account);
		$this->preferences
			->method('isEmailNotificationDisabled')
			->willReturnCallback(static fn (string $uid): bool => $uid === 'bob-uid' && $disabled);

		$this->listener->handle($this->invitation('account', 'bob-uid'));

		$this->assertCount($shouldDeliver ? 1 : 0, $this->deliveries);
		if ($shouldDeliver) {
			$this->assertSame('bob@example.org', $this->singleDelivery()->recipient);
		}
	}

	public function testSharedEmailCannotBypassAccountMailPreference(): void {
		$account = $this->account('bob-uid', 'shared@example.org');
		$this->userManager->method('get')->willReturn($account);
		$this->userManager->method('getByEmail')->willReturn([
			$account,
			$this->account('other-uid', 'shared@example.org'),
		]);
		$this->preferences
			->method('isEmailNotificationDisabled')
			->willReturnCallback(static fn (string $uid): bool => $uid === 'bob-uid');

		$this->listener->handle($this->invitation('account', 'bob-uid'));

		$this->assertSame([], $this->deliveries);
	}

	public function testMissingAccountDoesNotDispatch(): void {
		$this->userManager->method('get')->willReturn(null);

		$this->listener->handle($this->invitation('account', 'deleted-local-user'));

		$this->assertSame([], $this->deliveries);
	}

	public function testUnsupportedIdentificationMethodDoesNotDispatch(): void {
		$this->listener->handle($this->invitation('sms', '5511999999999'));

		$this->assertSame([], $this->deliveries);
	}

	public function testEmptyEmailDoesNotDispatch(): void {
		$this->listener->handle($this->invitation('email', ''));

		$this->assertSame([], $this->deliveries);
	}

	public function testDeletedSignerDoesNotDispatch(): void {
		$this->listener->handle($this->invitation('email', 'deleted@example.org', null, true));

		$this->assertSame([], $this->deliveries);
	}

	public static function emailCancellationPreferences(): array {
		return [
			'notifications enabled' => [false, true],
			'notifications disabled' => [true, false],
		];
	}

	#[DataProvider('emailCancellationPreferences')]
	public function testEmailSignerCancellationRespectsExistingAccountPreference(bool $disabled, bool $shouldDeliver): void {
		$account = $this->account('bob-uid', 'bob@example.org');
		$this->userManager
			->method('getByEmail')
			->willReturn([$account]);
		$this->preferences
			->method('isEmailNotificationDisabled')
			->willReturnCallback(static fn (string $uid): bool => $uid === 'bob-uid' && $disabled);

		$this->listener->handle($this->cancellation('email', 'bob@example.org'));

		$this->assertCount($shouldDeliver ? 1 : 0, $this->deliveries);
		if ($shouldDeliver) {
			$this->assertSame('bob@example.org', $this->singleDelivery()->recipient);
			$this->assertSame('sign_request_canceled', $this->singleDelivery()->eventType);
		}
	}

	public function testExternalEmailSignerReceivesCancellation(): void {
		$this->userManager->method('getByEmail')->with('carol@example.org')->willReturn([]);

		$this->listener->handle($this->cancellation('email', 'carol@example.org'));

		$this->assertSame('carol@example.org', $this->singleDelivery()->recipient);
	}

	public static function signedMailPreferences(): array {
		return [
			'notification disabled' => [true, false],
			'notification enabled' => [false, true],
		];
	}

	#[DataProvider('signedMailPreferences')]
	public function testSignedMailRespectsNotificationPreference(bool $disabled, bool $shouldDeliver): void {
		$this->preferences->method('isEmailNotificationDisabled')->willReturn($disabled);
		$owner = $this->account('admin', 'admin@example.org');

		$owner = $this->createMock(IUser::class);
		$owner->method('getEMailAddress')->willReturn('admin@email.tld');
		$owner->method('getDisplayName')->willReturn('Admin');

		$expectation = $shouldSend ? $this->once() : $this->never();
		$this->mailService->expects($expectation)
			->method('notifySignedUser');

		$this->listener->handle($this->createSignedEvent($owner));
	}

	private function createSignedEvent(IUser $owner): SignedEvent {
		$signRequest = new SignRequest();
		$signRequest->setId(42);
		$signRequest->setFileId(10);
		$signRequest->setDisplayName('Signer Name');

		$libreSignFile = new FileEntity();
		$libreSignFile->setId(10);
		$libreSignFile->setUuid('file-uuid');
		$libreSignFile->setName('Contract.pdf');
		$libreSignFile->setUserId('admin');

		$identifyEntity = new IdentifyMethodEntity();
		$identifyEntity->setIdentifierKey('account');
		$identifyEntity->setIdentifierValue('signer1');

		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getEntity')->willReturn($identifyEntity);

		$signedFile = $this->createMock(File::class);

		return new SignedEvent(
			$signRequest,
			$libreSignFile,
			$identifyMethod,
			$owner,
			$this->createMock(File::class),
		));

		$this->assertCount($shouldDeliver ? 1 : 0, $this->deliveries);
		if ($shouldDeliver) {
			$this->assertSame('admin@example.org', $this->singleDelivery()->recipient);
			$this->assertSame('file_signed', $this->singleDelivery()->eventType);
		}
	}

	private function request(): SignRequest {
		$request = new SignRequest();
		$request->setId(42);
		$request->setUuid('synthetic-request');
		$request->setDescription('Please sign');
		return $request;
	}

	private function invitation(
		string $method,
		string $value,
		?SignRequest $request = null,
		bool $deleted = false,
	): SendSignNotificationEvent {
		return new SendSignNotificationEvent(
			$request ?? $this->request(),
			new FileEntity(),
			$this->identifyMethod($method, $value, $deleted),
			'invitation-42',
		);
	}

	private function cancellation(string $method, string $value): SignRequestCanceledEvent {
		return new SignRequestCanceledEvent(
			$this->request(),
			new FileEntity(),
			$this->identifyMethod($method, $value),
		);
	}

	private function identifyMethod(string $method, string $value, bool $deleted = false): IIdentifyMethod {
		$entity = new IdentifyMethodEntity();
		$entity->setIdentifierKey($method);
		$entity->setIdentifierValue($value);
		if ($deleted) {
			$entity->setMetadata(['deleted_account' => true]);
		}
		$identify = $this->createMock(IIdentifyMethod::class);
		$identify->method('getName')->willReturn($method);
		$identify->method('getEntity')->willReturn($entity);
		return $identify;
	}

	private function account(string $uid, string $email): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getEMailAddress')->willReturn($email);
		return $user;
	}

	private function singleDelivery(): NotificationMessage {
		$this->assertCount(1, $this->deliveries);
		return $this->deliveries[0];
	}
}
