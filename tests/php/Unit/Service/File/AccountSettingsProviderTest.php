<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Service\File\AccountSettingsProvider;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\IAccountProperty;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;

final class AccountSettingsProviderTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IAccountManager|MockObject $accountManager;
	private IAppConfig|MockObject $appConfig;
	private IGroupManager|MockObject $groupManager;
	private Pkcs12Handler|MockObject $pkcs12Handler;

	public function setUp(): void {
		parent::setUp();
		$this->accountManager = $this->createMock(IAccountManager::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->pkcs12Handler = $this->createMock(Pkcs12Handler::class);
	}

	private function getService(): AccountSettingsProvider {
		return new AccountSettingsProvider(
			$this->accountManager,
			$this->appConfig,
			$this->groupManager,
			$this->pkcs12Handler,
		);
	}

	public function testGetPhoneNumber(): void {
		$user = $this->createMock(IUser::class);

		$accountProperty = $this->createMock(IAccountProperty::class);
		$accountProperty->method('getValue')->willReturn('123456789');

		$account = $this->createMock(IAccount::class);
		$account->method('getProperty')->with(IAccountManager::PROPERTY_PHONE)->willReturn($accountProperty);

		$this->accountManager->method('getAccount')->with($user)->willReturn($account);

		$service = $this->getService();
		$result = $service->getPhoneNumber($user);

		$this->assertEquals('123456789', $result);
	}

	public function testGetSettingsWithUserCanSignAndHasSignature(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');

		$this->appConfig->method('getValueArray')
			->with(Application::APP_ID, 'approval_group', ['admin'])
			->willReturn(['admin', 'users']);

		$this->groupManager->method('getUserGroupIds')
			->willReturn(['users', 'editors']);

		$this->pkcs12Handler->method('getPfxOfCurrentSigner')
			->with('user123')
			->willReturn('signature_content');

		$service = $this->getService();
		$result = $service->getSettings($user);

		$this->assertTrue($result['canRequestSign']);
		$this->assertTrue($result['hasSignatureFile']);
	}

	public function testGetSettingsWithUserCannotSign(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');

		$this->appConfig->method('getValueArray')
			->with(Application::APP_ID, 'approval_group', ['admin'])
			->willReturn(['admin']);

		$this->groupManager->method('getUserGroupIds')
			->willReturn(['users', 'editors']);

		$this->pkcs12Handler->method('getPfxOfCurrentSigner')
			->with('user123')
			->willReturn('signature_content');

		$service = $this->getService();
		$result = $service->getSettings($user);

		$this->assertFalse($result['canRequestSign']);
		$this->assertTrue($result['hasSignatureFile']);
	}

	public function testGetSettingsWithUserNoSignatureFile(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');

		$this->appConfig->method('getValueArray')
			->with(Application::APP_ID, 'approval_group', ['admin'])
			->willReturn(['users']);

		$this->groupManager->method('getUserGroupIds')
			->willReturn(['users']);

		$this->pkcs12Handler->method('getPfxOfCurrentSigner')
			->with('user123')
			->willThrowException(new LibresignException('No signature file'));

		$service = $this->getService();
		$result = $service->getSettings($user);

		$this->assertTrue($result['canRequestSign']);
		$this->assertFalse($result['hasSignatureFile']);
	}

	public function testGetSettingsWithNullUser(): void {
		$service = $this->getService();
		$result = $service->getSettings(null);

		$this->assertFalse($result['canRequestSign']);
		$this->assertFalse($result['hasSignatureFile']);
	}

	public function testGetSettingsWithEmptyAuthorizedGroups(): void {
		$user = $this->createMock(IUser::class);

		$this->appConfig->method('getValueArray')
			->with(Application::APP_ID, 'approval_group', ['admin'])
			->willReturn([]);

		$service = $this->getService();
		$result = $service->getSettings($user);

		$this->assertFalse($result['canRequestSign']);
	}
}
