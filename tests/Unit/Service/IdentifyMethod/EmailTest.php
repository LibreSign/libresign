<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdentifyMethod\Email;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Service\SessionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class EmailTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IdentifyService&MockObject $identifyService;
	private IdentifyMethodMapper&MockObject $identifyMethodMapper;
	private IRootFolder&MockObject $root;
	private ITimeFactory&MockObject $timeFactory;
	private SessionService&MockObject $sessionService;
	private FileElementMapper&MockObject $fileElementMapper;
	private IUserSession&MockObject $userSession;
	private LoggerInterface&MockObject $logger;
	private IL10N $l10n;

	public function setUp(): void {
		$this->identifyService = $this->createMock(IdentifyService::class);
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
		$this->identifyService->method('getL10n')->willReturn($this->l10n);
		$this->identifyService->method('getAppConfig')->willReturn($this->getMockAppConfig());
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->root = $this->createMock(IRootFolder::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->sessionService = $this->createMock(SessionService::class);
		$this->fileElementMapper = $this->createMock(FileElementMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function getClass(): Email {
		return new Email(
			$this->identifyService,
			$this->identifyMethodMapper,
			$this->root,
			$this->timeFactory,
			$this->sessionService,
			$this->fileElementMapper,
			$this->userSession,
			$this->logger,
		);
	}

	#[DataProvider('providerValidateToRequest')]
	public function testValidateToRequest(string $email, bool $isValid): void {
		if (!$isValid) {
			$this->expectException(LibresignException::class);
			$this->expectExceptionMessageMatches('/.*Invalid email.*/');
		} else {
			$this->expectNotToPerformAssertions();
		}

		$identifyMethod = $this->getClass();
		$identifyMethod->getEntity()->setIdentifierValue($email);
		$identifyMethod->validateToRequest();
	}

	public static function providerValidateToRequest(): array {
		return [
			'valid email' => ['email' => 'a@b.c', 'isValid' => true],
			'invalid email' => ['email' => 'invalid-email', 'isValid' => false],
		];
	}

	#[DataProvider('providerThrowIfNeedToCreateAccount')]
	public function testThrowIfNeedToCreateAccount(
		bool $isAuthenticated,
		bool $enabled,
		bool $canCreateAccount,
		bool $signTimeStarted,
		bool $emailExists,
		bool $isLoggedIn,
		string $errorMessage = '',
	): void {
		if ($errorMessage) {
			$this->expectException(LibresignException::class);
			$this->expectExceptionMessageMatches("/.*$errorMessage.*/");
		} else {
			$this->expectNotToPerformAssertions();
		}
		$user = $this->createMock(IUser::class);
		if ($isAuthenticated) {
			$this->userSession->method('getUser')->willReturn($user);
		} else {
			$this->userSession->method('getUser')->willReturn(null);
		}
		$this->identifyService->method('getSavedSettings')->willReturn([
			'email' => [
				'name' => 'email',
				'enabled' => $enabled,
				'can_create_account' => $canCreateAccount,
			],
		]);
		$this->sessionService->method('getSignStartTime')->willReturn($signTimeStarted ? 1 : 0);
		$this->identifyService->method('getSessionService')->willReturn($this->sessionService);
		$userByEmail = $this->createMock(IUserManager::class);
		$userByEmail->method('getByEmail')->willReturn($emailExists ? $user : null);
		$this->identifyService->method('getUserManager')->willReturn($userByEmail);
		$this->userSession->method('isLoggedIn')->willReturn($isLoggedIn);

		$identifyMethod = $this->getClass();
		self::invokePrivate($identifyMethod, 'throwIfNeedToCreateAccount');
	}

	public static function providerThrowIfNeedToCreateAccount(): array {
		return [
			'authenticated_user' => [true, false, false, false, false, false, ''],
			'invalid_method' => [false, false, false, false, false, false, 'Invalid identification method'],
			'method_enabled_no_account_creation' => [false, true, false, false, false, false, ''],
			'method_enabled_sign_time_started' => [false, true, true, true, false, false, ''],
			'method_enabled_account_creation_required' => [false, true, true, false, false, false, 'You need to create an account to sign this file.'],
			'method_enabled_file_not_owned' => [false, true, true, false, true, true, 'This is not your file'],
			'method_enabled_user_exists_not_logged_in' => [false, true, true, false, true, false, 'User already exists. Please login.'],
		];
	}
}
