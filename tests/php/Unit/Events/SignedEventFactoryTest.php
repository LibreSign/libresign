<?php

declare(strict_types=1);

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Events\SignedEventFactory;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\Files\File;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

final class SignedEventFactoryTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IUserManager&MockObject $userManager;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private IL10N $l10n;

	public function setUp(): void {
		parent::setUp();
		$this->userManager = $this->createMock(IUserManager::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(\OCA\Libresign\AppInfo\Application::APP_ID);
	}

	private function getInstance(array $methods = []): SignedEventFactory|MockObject {
		if ($methods) {
			return $this->getMockBuilder(SignedEventFactory::class)
				->setConstructorArgs([
					$this->userManager,
					$this->identifyMethodService,
					$this->l10n,
				])
				->onlyMethods($methods)
				->getMock();
		}
		return new SignedEventFactory(
			$this->userManager,
			$this->identifyMethodService,
			$this->l10n,
		);
	}

	public function testMakeReturnsCorrectSignedEvent(): void {
		$instance = $this->getInstance(['getIdentifyMethod', 'getUser']);

		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$instance->method('getIdentifyMethod')->willReturn($identifyMethod);

		$user = $this->createMock(IUser::class);
		$instance->method('getUser')->willReturn($user);

		$signRequest = new SignRequest();
		$libreSignFile = new FileEntity();
		$signedFile = $this->createMock(File::class);

		$event = $instance->make(
			$signRequest,
			$libreSignFile,
			$signedFile,
		);

		$this->assertEquals($signRequest, $event->getSignRequest());
		$this->assertEquals($signedFile, $event->getSignedFile());
		$this->assertEquals($identifyMethod, $event->getIdentifyMethod());
		$this->assertEquals($user, $event->getUser());
		$this->assertEquals($libreSignFile, $event->getLibreSignFile());
	}
}
