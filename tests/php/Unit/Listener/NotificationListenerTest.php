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
use OCA\Libresign\Listener\NotificationListener;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\NotificationPreferenceResolver;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Notification\IManager as NotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NotificationListenerTest extends TestCase {
	private NotificationManager&MockObject $notificationManager;
	private INotification&MockObject $notification;
	private NotificationPreferenceResolver&MockObject $notificationPreferenceResolver;
	private NotificationListener $listener;

	protected function setUp(): void {
		$this->notificationManager = $this->createMock(NotificationManager::class);
		$this->notification = $this->createMock(INotification::class);
		$this->notificationPreferenceResolver = $this->createMock(NotificationPreferenceResolver::class);
		$this->notification->method('setApp')->willReturnSelf();
		$this->notification->method('setObject')->willReturnSelf();
		$this->notification->method('setDateTime')->willReturnSelf();
		$this->notification->method('setUser')->willReturnSelf();
		$this->notification->method('setSubject')->willReturnSelf();

		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('now')->willReturn(new \DateTimeImmutable('2026-06-19T00:00:00Z'));

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRouteAbsolute')
			->willReturn('https://example.test/validation/file-uuid');

		$this->listener = new NotificationListener(
			$this->notificationManager,
			$this->createMock(IUserSession::class),
			$timeFactory,
			$urlGenerator,
			$this->createMock(SignRequestMapper::class),
			$this->notificationPreferenceResolver,
		);
	}

	public static function provideSignedNotificationPreferenceScenarios(): array {
		return [
			'notification disabled' => [true, false],
			'notification enabled' => [false, true],
		];
	}

	#[DataProvider('provideSignedNotificationPreferenceScenarios')]
	public function testSignedNotificationRespectsNotificationPreference(
		bool $notificationDisabled,
		bool $shouldNotify,
	): void {
		$this->notificationPreferenceResolver->expects($this->once())
			->method('isInAppNotificationDisabled')
			->with('admin', SignedEvent::FILE_SIGNED, true)
			->willReturn($notificationDisabled);

		$this->notificationManager->expects($shouldNotify ? $this->once() : $this->never())
			->method('createNotification')
			->willReturn($this->notification);
		$this->notificationManager->expects($shouldNotify ? $this->once() : $this->never())
			->method('notify')
			->with($this->notification);

		$this->listener->handle($this->createSignedEvent());
	}

	private function createSignedEvent(): SignedEvent {
		$signRequest = new SignRequest();
		$signRequest->setId(42);
		$signRequest->setDisplayName('Signer Name');
		$signRequest->setFileId(10);

		$libreSignFile = new FileEntity();
		$libreSignFile->setId(10);
		$libreSignFile->setNodeId(99);
		$libreSignFile->setUuid('file-uuid');
		$libreSignFile->setName('Contract.pdf');
		$libreSignFile->setUserId('admin');

		$identifyEntity = new IdentifyMethodEntity();
		$identifyEntity->setId(7);
		$identifyEntity->setIdentifierKey('account');
		$identifyEntity->setIdentifierValue('signer1');

		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getEntity')->willReturn($identifyEntity);

		return new SignedEvent(
			$signRequest,
			$libreSignFile,
			$identifyMethod,
			$this->createMock(\OCP\IUser::class),
			$this->createMock(File::class),
		);
	}
}
