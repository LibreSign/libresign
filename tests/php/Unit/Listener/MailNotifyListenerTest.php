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
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Events\SignRequestCanceledEvent;
use OCA\Libresign\Listener\MailNotifyListener;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
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
	private MailService&MockObject $mailService;
	private IUserManager&MockObject $userManager;
	private NotificationPreferenceResolver&MockObject $preferences;
	private SignRequestMapper&MockObject $signRequestMapper;
	private MailNotifyListener $listener;
	private bool $firstNotification = true;

	protected function setUp(): void {
		$this->mailService = $this->createMock(MailService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->preferences = $this->createMock(NotificationPreferenceResolver::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->signRequestMapper->method('incrementNotificationCounter')
			->willReturnCallback(fn (): bool => $this->firstNotification);

		$this->listener = new MailNotifyListener(
			$this->userManager,
			$this->createMock(IdentifyService::class),
			$this->mailService,
			$this->signRequestMapper,
			$this->createMock(LoggerInterface::class),
			$this->preferences,
		);
	}

	public function testEmailInvitationIgnoresLinkedAccountOptOut(): void {
		$this->userManager->expects($this->never())->method('getByEmail');
		$this->userManager->expects($this->never())->method('get');
		$this->preferences->expects($this->never())->method('isEmailNotificationDisabled');
		$this->preferences->expects($this->once())
			->method('isEmailNotificationGloballyDisabled')
			->with(SendSignNotificationEvent::FILE_TO_SIGN)
			->willReturn(false);
		$this->mailService->expects($this->once())
			->method('notifyUnsignedUser')
			->with($this->isInstanceOf(SignRequest::class), 'bob@example.org', 'Review document');

		$this->listener->handle($this->invitation('email', 'bob@example.org'));
	}

	public function testEmailInvitationHonorsAdminDisabledChannel(): void {
		$this->preferences->expects($this->once())
			->method('isEmailNotificationGloballyDisabled')
			->with(SendSignNotificationEvent::FILE_TO_SIGN)
			->willReturn(true);
		$this->userManager->expects($this->never())->method('getByEmail');
		$this->signRequestMapper->expects($this->never())->method('getById');
		$this->mailService->expects($this->never())->method('notifyUnsignedUser');
		$this->mailService->expects($this->never())->method('notifySignDataUpdated');

		$this->listener->handle($this->invitation('email', 'bob@example.org'));
	}

	public function testExternalEmailInvitationIsSentWithoutAccountLookup(): void {
		$this->userManager->expects($this->never())->method('getByEmail');
		$this->mailService->expects($this->once())
			->method('notifyUnsignedUser')
			->with($this->isInstanceOf(SignRequest::class), 'external@example.org', 'Review document');

		$this->listener->handle($this->invitation('email', 'external@example.org'));
	}

	public function testReminderUsesUpdatedMailTemplate(): void {
		$this->firstNotification = false;
		$this->mailService->expects($this->never())->method('notifyUnsignedUser');
		$this->mailService->expects($this->once())
			->method('notifySignDataUpdated')
			->with($this->isInstanceOf(SignRequest::class), 'bob@example.org', 'Review document');

		$this->listener->handle($this->invitation('email', 'bob@example.org'));
	}

	public static function accountPreferences(): array {
		return [
			'account allows email' => [false, true],
			'account disables email' => [true, false],
		];
	}

	#[DataProvider('accountPreferences')]
	public function testAccountInvitationUsesUidAndItsPreference(bool $disabled, bool $shouldSend): void {
		$this->userManager->method('get')->with('account-uid')
			->willReturn($this->account('account-uid', 'shared@example.org'));
		$this->userManager->expects($this->never())->method('getByEmail');
		$this->preferences->expects($this->once())
			->method('isEmailNotificationDisabled')
			->with('account-uid', SendSignNotificationEvent::FILE_TO_SIGN, true)
			->willReturn($disabled);
		$this->mailService->expects($shouldSend ? $this->once() : $this->never())
			->method('notifyUnsignedUser')
			->with($this->anything(), 'shared@example.org', 'Review document');

		$this->listener->handle($this->invitation('account', 'account-uid'));
	}

	public function testMissingAccountIsSkippedWithoutDereference(): void {
		$this->userManager->method('get')->with('absent-uid')->willReturn(null);
		$this->mailService->expects($this->never())->method('notifyUnsignedUser');

		$this->listener->handle($this->invitation('account', 'absent-uid'));
	}

	public function testAccountWithoutEmailIsSkipped(): void {
		$this->userManager->method('get')->with('account-uid')
			->willReturn($this->account('account-uid', ''));
		$this->mailService->expects($this->never())->method('notifyUnsignedUser');

		$this->listener->handle($this->invitation('account', 'account-uid'));
	}

	public function testEmptyAndUnsupportedDestinationsAreSkipped(): void {
		$this->mailService->expects($this->never())->method('notifyUnsignedUser');

		$this->listener->handle($this->invitation('email', ''));
		$this->listener->handle($this->invitation('phone', '+1234567890'));
	}

	public function testDeletedIdentityDoesNotReceiveInvitationOrCancellation(): void {
		$this->mailService->expects($this->never())->method('notifyUnsignedUser');
		$this->mailService->expects($this->never())->method('notifyCanceledRequest');

		$this->listener->handle($this->invitation('account', 'deleted-uid', true));
		$this->listener->handle($this->cancellation('account', 'deleted-uid', true));
	}

	public static function cancellationPreferences(): array {
		return [
			'linked account allows cancellation email' => [false, true],
			'linked account disables cancellation email' => [true, false],
		];
	}

	#[DataProvider('cancellationPreferences')]
	public function testEmailCancellationRetainsUniqueLinkedAccountPreference(bool $disabled, bool $shouldSend): void {
		$this->userManager->method('getByEmail')->with('linked@example.org')
			->willReturn([$this->account('account-uid', 'linked@example.org')]);
		$this->preferences->expects($this->once())
			->method('isEmailNotificationDisabled')
			->with('account-uid', SignRequestCanceledEvent::SIGN_REQUEST_CANCELED, true)
			->willReturn($disabled);
		$this->mailService->expects($shouldSend ? $this->once() : $this->never())
			->method('notifyCanceledRequest')
			->with($this->anything(), 'linked@example.org', $this->isInstanceOf(FileEntity::class));

		$this->listener->handle($this->cancellation('email', 'linked@example.org'));
	}

	public function testExternalEmailCancellationIsSent(): void {
		$this->userManager->method('getByEmail')->with('external@example.org')->willReturn([]);
		$this->preferences->expects($this->never())->method('isEmailNotificationDisabled');
		$this->mailService->expects($this->once())
			->method('notifyCanceledRequest')
			->with($this->anything(), 'external@example.org', $this->isInstanceOf(FileEntity::class));

		$this->listener->handle($this->cancellation('email', 'external@example.org'));
	}

	public function testSharedEmailCancellationIsNotSuppressedByOneUser(): void {
		$this->userManager->method('getByEmail')->with('shared@example.org')
			->willReturn([
				$this->account('first', 'shared@example.org'),
				$this->account('second', 'shared@example.org'),
			]);
		$this->preferences->expects($this->never())->method('isEmailNotificationDisabled');
		$this->mailService->expects($this->once())
			->method('notifyCanceledRequest')
			->with($this->anything(), 'shared@example.org', $this->isInstanceOf(FileEntity::class));

		$this->listener->handle($this->cancellation('email', 'shared@example.org'));
	}

	#[DataProvider('accountPreferences')]
	public function testAccountCancellationChecksSelectedUid(bool $disabled, bool $shouldSend): void {
		$this->userManager->method('get')->with('account-uid')
			->willReturn($this->account('account-uid', 'shared@example.org'));
		$this->userManager->expects($this->never())->method('getByEmail');
		$this->preferences->expects($this->once())
			->method('isEmailNotificationDisabled')
			->with('account-uid', SignRequestCanceledEvent::SIGN_REQUEST_CANCELED, true)
			->willReturn($disabled);
		$this->mailService->expects($shouldSend ? $this->once() : $this->never())
			->method('notifyCanceledRequest')
			->with($this->anything(), 'shared@example.org', $this->isInstanceOf(FileEntity::class));

		$this->listener->handle($this->cancellation('account', 'account-uid'));
	}

	public static function signedMailPreferences(): array {
		return [
			'owner enables signed mail' => [false, true],
			'owner disables signed mail' => [true, false],
		];
	}

	#[DataProvider('signedMailPreferences')]
	public function testSignedFileMailStillUsesOwnerPreference(bool $disabled, bool $shouldSend): void {
		$this->preferences->expects($this->once())
			->method('isEmailNotificationDisabled')
			->with('admin', SignedEvent::FILE_SIGNED, true)
			->willReturn($disabled);
		$owner = $this->account('admin', 'admin@example.org');
		$owner->method('getDisplayName')->willReturn('Admin');
		$this->mailService->expects($shouldSend ? $this->once() : $this->never())
			->method('notifySignedUser')
			->with($this->anything(), 'admin@example.org', $this->anything(), 'Admin');
		$this->listener->handle(new SignedEvent(
			$this->request(),
			$this->file(),
			$this->identifyMethod('account', 'signer-uid'),
			$owner,
			$this->createMock(File::class),
		));
	}

	private function invitation(string $method, string $value, bool $deleted = false): SendSignNotificationEvent {
		return new SendSignNotificationEvent(
			$this->request(), $this->file(), $this->identifyMethod($method, $value, $deleted),
		);
	}

	private function cancellation(string $method, string $value, bool $deleted = false): SignRequestCanceledEvent {
		return new SignRequestCanceledEvent(
			$this->request(), $this->file(), $this->identifyMethod($method, $value, $deleted),
		);
	}

	private function request(): SignRequest {
		$request = new SignRequest();
		$request->setId(42);
		$request->setFileId(10);
		$request->setDescription('Review document');
		return $request;
	}

	private function file(): FileEntity {
		$file = new FileEntity();
		$file->setId(10);
		$file->setUserId('admin');
		$file->setName('Contract.pdf');
		return $file;
	}

	private function identifyMethod(string $method, string $value, bool $deleted = false): IIdentifyMethod {
		$entity = new IdentifyMethodEntity();
		$entity->setIdentifierKey($method);
		$entity->setIdentifierValue($value);
		if ($deleted) {
			$entity->setMetadata(['deleted_account' => [
				'account' => $value,
				'display_name' => 'Deleted user',
			]]);
		}
		$identify = $this->createMock(IIdentifyMethod::class);
		$identify->method('getName')->willReturn($method);
		$identify->method('getEntity')->willReturn($entity);
		return $identify;
	}

	private function account(string $uid, string $email): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getEMailAddress')->willReturn($email);
		return $user;
	}
}
