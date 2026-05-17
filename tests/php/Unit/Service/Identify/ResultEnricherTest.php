<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Identify;

use OCA\Libresign\Service\Identify\ResultEnricher;
use OCA\Libresign\Service\IdentifyMethod\Account;
use OCA\Libresign\Service\IdentifyMethod\Email;
use OCP\Config\IUserConfig;
use OCP\IAppConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ResultEnricherTest extends TestCase {
	private ResultEnricher $enricher;
	private IUserSession&MockObject $userSession;
	private IUserManager&MockObject $userManager;
	private IAppConfig&MockObject $appConfig;
	private IUserConfig&MockObject $userConfig;
	private Account&MockObject $accountMethod;
	private Email&MockObject $emailMethod;
	private IUser&MockObject $currentUser;

	protected function setUp(): void {
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->userConfig = $this->createMock(IUserConfig::class);
		$this->accountMethod = $this->createMock(Account::class);
		$this->emailMethod = $this->createMock(Email::class);
		$this->currentUser = $this->createMock(IUser::class);

		$this->enricher = new ResultEnricher(
			$this->userSession,
			$this->userManager,
			$this->emailMethod,
			$this->accountMethod,
			$this->appConfig,
			$this->userConfig,
		);
	}

	public function testAddHerselfAccountWhenEnabled(): void {
		$this->userSession->method('getUser')
			->willReturn($this->currentUser);
		$this->currentUser->method('getUID')
			->willReturn('john');
		$this->currentUser->method('getDisplayName')
			->willReturn('John Doe');
		$this->currentUser->method('getEMailAddress')
			->willReturn('john@company.com');

		$this->accountMethod->method('getSettings')
			->willReturn(['enabled' => true]);

		$result = $this->enricher->addHerselfAccount([], 'john');

		$this->assertCount(1, $result);
		$this->assertEquals('john', $result[0]['identify']);
		$this->assertEquals('account', $result[0]['method']);
		$this->assertFalse($result[0]['isNoUser']);
		$this->assertEquals('account', $result[0]['iconName']);
	}

	#[DataProvider('providerAddHerselfAccountDisabledOrDuplicate')]
	public function testAddHerselfAccountDisabledOrDuplicate(bool $enabled, array $existingResults, int $expectedCount): void {
		$this->accountMethod->method('getSettings')
			->willReturn(['enabled' => $enabled]);

		if ($enabled) {
			$this->userSession->method('getUser')
				->willReturn($this->currentUser);
			$this->currentUser->method('getUID')
				->willReturn('john');
		}

		$result = $this->enricher->addHerselfAccount($existingResults, 'john');
		$this->assertCount($expectedCount, $result);
	}

	public static function providerAddHerselfAccountDisabledOrDuplicate(): array {
		return [
			'disabled' => [false, [], 0],
			'already exists' => [true, [['identify' => 'john', 'method' => 'account']], 1],
		];
	}

	public function testAddHerselfEmailWhenEnabled(): void {
		$this->userSession->method('getUser')
			->willReturn($this->currentUser);
		$this->currentUser->method('getEMailAddress')
			->willReturn('john@company.com');
		$this->currentUser->method('getDisplayName')
			->willReturn('John Doe');

		$this->emailMethod->method('getSettings')
			->willReturn(['enabled' => true]);

		$result = $this->enricher->addHerselfEmail([], 'john@company.com');

		$this->assertCount(1, $result);
		$this->assertEquals('john@company.com', $result[0]['identify']);
		$this->assertEquals('email', $result[0]['method']);
		$this->assertTrue($result[0]['isNoUser']);
		$this->assertEquals('email', $result[0]['iconName']);
	}

	#[DataProvider('providerAddHerselfEmailSkipScenarios')]
	public function testAddHerselfEmailSkipScenarios(?string $userEmail, bool $enabled, int $expectedCount): void {
		$this->userSession->method('getUser')
			->willReturn($this->currentUser);
		$this->currentUser->method('getEMailAddress')
			->willReturn($userEmail);

		$this->emailMethod->method('getSettings')
			->willReturn(['enabled' => $enabled]);

		$result = $this->enricher->addHerselfEmail([], 'john');
		$this->assertCount($expectedCount, $result);
	}

	public static function providerAddHerselfEmailSkipScenarios(): array {
		return [
			'disabled' => [null, false, 0],
			'no email' => [null, true, 0],
			'search by display name' => ['john@company.com', true, 1],
		];
	}

	#[DataProvider('providerAddHerselfAccountMethodFilter')]
	public function testAddHerselfAccountMethodFilter(string $method, int $expectedCount): void {
		$this->userSession->method('getUser')
			->willReturn($this->currentUser);
		$this->currentUser->method('getUID')
			->willReturn('john');
		$this->currentUser->method('getDisplayName')
			->willReturn('John Doe');

		$this->accountMethod->method('getSettings')
			->willReturn(['enabled' => true]);

		$result = $this->enricher->addHerselfAccount([], 'john', $method);
		$this->assertCount($expectedCount, $result);
	}

	public static function providerAddHerselfAccountMethodFilter(): array {
		return [
			'no method filter' => ['', 1],
			'matching account method' => ['account', 1],
			'non-matching email method' => ['email', 0],
			'non-matching phone method' => ['whatsapp', 0],
		];
	}

	#[DataProvider('providerAddHerselfEmailMethodFilter')]
	public function testAddHerselfEmailMethodFilter(string $method, int $expectedCount): void {
		$this->userSession->method('getUser')
			->willReturn($this->currentUser);
		$this->currentUser->method('getEMailAddress')
			->willReturn('john@company.com');
		$this->currentUser->method('getDisplayName')
			->willReturn('John Doe');

		$this->emailMethod->method('getSettings')
			->willReturn(['enabled' => true]);

		$result = $this->enricher->addHerselfEmail([], 'john@company.com', $method);
		$this->assertCount($expectedCount, $result);
	}

	public static function providerAddHerselfEmailMethodFilter(): array {
		return [
			'no method filter' => ['', 1],
			'matching email method' => ['email', 1],
			'non-matching account method' => ['account', 0],
			'non-matching phone method' => ['sms', 0],
		];
	}

	#[DataProvider('providerAddEmailNotificationPreference')]
	public function testAddEmailNotificationPreference(
		string $method,
		string $adminSetting,
		string $userSetting,
		bool $shouldHaveEmail,
		?bool $acceptsNotifications,
	): void {
		if ($method === 'account') {
			$user = $this->createMock(IUser::class);
			$user->method('getEMailAddress')
				->willReturn('john@company.com');
			$user->method('getUID')
				->willReturn('john');

			$this->userManager->method('get')
				->with('john')
				->willReturn($user);

			$this->appConfig->method('getValueString')
				->with('activity', 'notify_email_libresign_file_to_sign', '1')
				->willReturn($adminSetting);

			$this->userConfig->method('getValueString')
				->with('john', 'activity', 'notify_email_libresign_file_to_sign', '')
				->willReturn($userSetting);
		}

		$list = [
			['identify' => $method === 'account' ? 'john' : 'test@example.com', 'method' => $method],
		];

		$result = $this->enricher->addEmailNotificationPreference($list);

		if ($method === 'account') {
			$this->assertSame($acceptsNotifications, $result[0]['acceptsEmailNotifications']);
			if ($shouldHaveEmail) {
				$this->assertEquals('john@company.com', $result[0]['emailAddress']);
			} else {
				$this->assertArrayNotHasKey('emailAddress', $result[0]);
			}
		} else {
			$this->assertArrayNotHasKey('emailAddress', $result[0]);
			$this->assertArrayNotHasKey('acceptsEmailNotifications', $result[0]);
		}
	}

	public static function providerAddEmailNotificationPreference(): array {
		return [
			'account with email notifications enabled' => ['account', '1', '1', true, true],
			'account with user setting disabled' => ['account', '1', '0', false, false],
			'account with global setting disabled' => ['account', '0', '1', false, false],
			'email method' => ['email', '1', '1', false, null],
			'phone method' => ['sms', '1', '1', false, null],
		];
	}

	public function testAddEmailNotificationPreferenceWhenUserNotFound(): void {
		$this->userManager->method('get')
			->with('john')
			->willReturn(null);

		$list = [
			['identify' => 'john', 'method' => 'account'],
		];

		$result = $this->enricher->addEmailNotificationPreference($list);
		$this->assertArrayNotHasKey('emailAddress', $result[0]);
		$this->assertArrayNotHasKey('acceptsEmailNotifications', $result[0]);
	}

	public function testAddEmailNotificationPreferenceWhenAccountHasNoEmail(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')
			->willReturn(null);

		$this->userManager->method('get')
			->with('john')
			->willReturn($user);

		$list = [
			['identify' => 'john', 'method' => 'account'],
		];

		$result = $this->enricher->addEmailNotificationPreference($list);
		$this->assertArrayNotHasKey('emailAddress', $result[0]);
		$this->assertArrayNotHasKey('acceptsEmailNotifications', $result[0]);
	}
}
