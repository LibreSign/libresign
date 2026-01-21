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
	private Account&MockObject $accountMethod;
	private Email&MockObject $emailMethod;
	private IUser&MockObject $currentUser;

	protected function setUp(): void {
		parent::setUp();
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->accountMethod = $this->createMock(Account::class);
		$this->emailMethod = $this->createMock(Email::class);
		$this->currentUser = $this->createMock(IUser::class);

		$this->enricher = new ResultEnricher(
			$this->userSession,
			$this->userManager,
			$this->emailMethod,
			$this->accountMethod,
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
		$this->assertEquals('john', $result[0]['id']);
		$this->assertEquals('account', $result[0]['method']);
		$this->assertFalse($result[0]['isNoUser']);
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
			'already exists' => [true, [['id' => 'john', 'method' => 'account']], 1],
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
		$this->assertEquals('john@company.com', $result[0]['id']);
		$this->assertEquals('email', $result[0]['method']);
		$this->assertTrue($result[0]['isNoUser']);
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

	#[DataProvider('providerAddEmailNotificationPreference')]
	public function testAddEmailNotificationPreference(string $method, bool $shouldHaveEmail): void {
		if ($method === 'account') {
			$user = $this->createMock(IUser::class);
			$user->method('getEMailAddress')
				->willReturn('john@company.com');
			$user->method('getUID')
				->willReturn('john');

			$this->userManager->method('get')
				->with('john')
				->willReturn($user);
		}

		$list = [
			['id' => $method === 'account' ? 'john' : 'test@example.com', 'method' => $method],
		];

		$result = $this->enricher->addEmailNotificationPreference($list);

		if ($shouldHaveEmail) {
			$this->assertEquals('john@company.com', $result[0]['emailAddress']);
		} else {
			$this->assertArrayNotHasKey('emailAddress', $result[0]);
		}
	}

	public static function providerAddEmailNotificationPreference(): array {
		return [
			'account with email' => ['account', true],
			'email method' => ['email', false],
			'phone method' => ['sms', false],
		];
	}

	public function testAddEmailNotificationPreferenceWhenUserNotFound(): void {
		$this->userManager->method('get')
			->with('john')
			->willReturn(null);

		$list = [
			['id' => 'john', 'method' => 'account'],
		];

		$result = $this->enricher->addEmailNotificationPreference($list);
		$this->assertArrayNotHasKey('emailAddress', $result[0]);
	}

	public function testAddEmailNotificationPreferenceWhenAccountHasNoEmail(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')
			->willReturn(null);

		$this->userManager->method('get')
			->with('john')
			->willReturn($user);

		$list = [
			['id' => 'john', 'method' => 'account'],
		];

		$result = $this->enricher->addEmailNotificationPreference($list);
		$this->assertArrayNotHasKey('emailAddress', $result[0]);
	}
}

