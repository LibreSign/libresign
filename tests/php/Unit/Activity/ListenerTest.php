<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Activity;

use OCA\Libresign\Activity\Listener;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\IdentifyMethod as IdentifyMethodEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ListenerTest extends TestCase {
	private IManager&MockObject $activityManager;
	private IUserSession&MockObject $userSession;
	private IURLGenerator&MockObject $urlGenerator;
	private SignRequestMapper&MockObject $signRequestMapper;
	private Listener $listener;

	protected function setUp(): void {
		$this->activityManager = $this->createMock(IManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);

		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getTime')->willReturn(1718770000);

		$this->urlGenerator->method('linkToRouteAbsolute')
			->willReturnCallback(static function (string $route, array $params = []): string {
				if ($route === 'libresign.page.indexFPath') {
					return 'https://example.test/apps/libresign/f/' . ($params['path'] ?? '');
				}
				if ($route === 'libresign.page.sign') {
					return 'https://example.test/apps/libresign/p/sign/' . ($params['uuid'] ?? '');
				}
				if ($route === 'libresign.page.validationFilePublic') {
					return 'https://example.test/apps/libresign/p/validation/' . ($params['uuid'] ?? '');
				}
				return 'https://example.test/fallback';
			});

		$this->listener = new Listener(
			$this->activityManager,
			$this->userSession,
			$this->createMock(LoggerInterface::class),
			$timeFactory,
			$this->createMock(AccountService::class),
			$this->urlGenerator,
			$this->signRequestMapper,
		);
	}

	public function testAccountObserverActivityUsesAuthenticatedFilesListLink(): void {
		$actor = $this->createMock(IUser::class);
		$actor->method('getUID')->willReturn('admin');
		$actor->method('getDisplayName')->willReturn('Admin');
		$this->userSession->method('getUser')->willReturn($actor);

		$this->signRequestMapper->expects($this->once())
			->method('incrementNotificationCounter')
			->willReturn(true);

		$activityEvent = $this->createMock(IEvent::class);
		$activityEvent->method('setApp')->willReturnSelf();
		$activityEvent->method('setType')->willReturnSelf();
		$activityEvent->method('setAuthor')->willReturnSelf();
		$activityEvent->method('setObject')->willReturnSelf();
		$activityEvent->method('setTimestamp')->willReturnSelf();
		$activityEvent->method('setAffectedUser')->willReturnSelf();
		$activityEvent->method('setGenerateNotification')->willReturnSelf();

		$capturedSubjectParams = null;
		$activityEvent->expects($this->once())
			->method('setSubject')
			->with(
				$this->anything(),
				$this->callback(static function (array $params) use (&$capturedSubjectParams): bool {
					$capturedSubjectParams = $params;
					return true;
				}),
			)
			->willReturnSelf();

		$this->activityManager->expects($this->once())
			->method('generateEvent')
			->willReturn($activityEvent);
		$this->activityManager->expects($this->once())
			->method('publish')
			->with($activityEvent);

		$this->listener->handle($this->createObserverSignNotificationEvent());

		$this->assertIsArray($capturedSubjectParams);
		$this->assertSame(
			'https://example.test/apps/libresign/f/filelist/sign?uuid=file-uuid',
			$capturedSubjectParams['file']['link'] ?? null,
		);
		$this->assertStringNotContainsString('/p/validation/', $capturedSubjectParams['file']['link'] ?? '');
	}

	private function createObserverSignNotificationEvent(): SendSignNotificationEvent {
		$signRequest = new SignRequest();
		$signRequest->setId(42);
		$signRequest->setUuid('sign-request-uuid');
		$signRequest->setDisplayName('Observer Name');
		$signRequest->setFileId(10);
		$signRequest->setParticipantRole('observer');

		$libreSignFile = new FileEntity();
		$libreSignFile->setId(10);
		$libreSignFile->setNodeId(99);
		$libreSignFile->setUuid('file-uuid');
		$libreSignFile->setName('Contract.pdf');
		$libreSignFile->setUserId('admin');

		$identifyEntity = new IdentifyMethodEntity();
		$identifyEntity->setId(7);
		$identifyEntity->setIdentifierKey('account');
		$identifyEntity->setIdentifierValue('observer1');

		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getName')->willReturn('account');
		$identifyMethod->method('getEntity')->willReturn($identifyEntity);

		return new SendSignNotificationEvent(
			$signRequest,
			$libreSignFile,
			$identifyMethod,
		);
	}
}
