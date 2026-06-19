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
use OCA\Libresign\Listener\MailNotifyListener;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\NotificationPreferenceResolver;
use OCP\Files\File;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MailNotifyListenerTest extends TestCase {
	private MailService&MockObject $mailService;
	private IUserManager&MockObject $userManager;
	private NotificationPreferenceResolver&MockObject $notificationPreferenceResolver;
	private MailNotifyListener $listener;

	public function setUp(): void {
		$this->mailService = $this->createMock(MailService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->notificationPreferenceResolver = $this->createMock(NotificationPreferenceResolver::class);
		$userSession = $this->createMock(IUserSession::class);
		$identifyService = $this->createMock(IdentifyService::class);
		$signRequestMapper = $this->createMock(SignRequestMapper::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->listener = new MailNotifyListener(
			$userSession,
			$this->userManager,
			$identifyService,
			$this->mailService,
			$signRequestMapper,
			$logger,
			$this->notificationPreferenceResolver,
		);
	}

	public static function provideSignedMailPreferenceScenarios(): array {
		return [
			'notification disabled' => [true, false],
			'notification enabled' => [false, true],
		];
	}

	#[DataProvider('provideSignedMailPreferenceScenarios')]
	public function testSignedMailRespectsNotificationPreference(
		bool $notificationDisabled,
		bool $shouldSend,
	): void {
		$this->notificationPreferenceResolver->expects($this->once())
			->method('isEmailNotificationDisabled')
			->with('admin', SignedEvent::FILE_SIGNED, true)
			->willReturn($notificationDisabled);

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
			$signedFile,
		);
	}
}
