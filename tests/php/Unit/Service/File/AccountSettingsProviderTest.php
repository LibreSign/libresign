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
use PHPUnit\Framework\Attributes\DataProvider;
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

	public static function providerGetSettings(): array {
		return [
			'user in authorized group with signature file' => [
				'hasUser' => true,
				'approvalGroups' => ['admin', 'users'],
				'userGroups' => ['users', 'editors'],
				'hasPfx' => true,
				'expectedCanRequestSign' => true,
				'expectedHasSignatureFile' => true,
				'expectedIsApprover' => true,
			],
			'user not in authorized group with signature file' => [
				'hasUser' => true,
				'approvalGroups' => ['admin'],
				'userGroups' => ['users', 'editors'],
				'hasPfx' => true,
				'expectedCanRequestSign' => false,
				'expectedHasSignatureFile' => true,
				'expectedIsApprover' => false,
			],
			'user in authorized group without signature file' => [
				'hasUser' => true,
				'approvalGroups' => ['users'],
				'userGroups' => ['users'],
				'hasPfx' => false,
				'expectedCanRequestSign' => true,
				'expectedHasSignatureFile' => false,
				'expectedIsApprover' => true,
			],
			'null user returns all false' => [
				'hasUser' => false,
				'approvalGroups' => [],
				'userGroups' => [],
				'hasPfx' => false,
				'expectedCanRequestSign' => false,
				'expectedHasSignatureFile' => false,
				'expectedIsApprover' => false,
			],
			'empty approval groups' => [
				'hasUser' => true,
				'approvalGroups' => [],
				'userGroups' => ['users'],
				'hasPfx' => true,
				'expectedCanRequestSign' => false,
				'expectedHasSignatureFile' => true,
				'expectedIsApprover' => false,
			],
			'user in one of multiple approval groups without signature' => [
				'hasUser' => true,
				'approvalGroups' => ['admin', 'approvers'],
				'userGroups' => ['approvers'],
				'hasPfx' => false,
				'expectedCanRequestSign' => true,
				'expectedHasSignatureFile' => false,
				'expectedIsApprover' => true,
			],
			'user in multiple matching groups' => [
				'hasUser' => true,
				'approvalGroups' => ['admin', 'managers'],
				'userGroups' => ['admin', 'managers', 'users'],
				'hasPfx' => true,
				'expectedCanRequestSign' => true,
				'expectedHasSignatureFile' => true,
				'expectedIsApprover' => true,
			],
		];
	}

	#[DataProvider('providerGetSettings')]
	public function testGetSettings(
		bool $hasUser,
		array $approvalGroups,
		array $userGroups,
		bool $hasPfx,
		bool $expectedCanRequestSign,
		bool $expectedHasSignatureFile,
		bool $expectedIsApprover,
	): void {
		$user = null;
		if ($hasUser) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('testuser');

			$this->appConfig->method('getValueArray')
				->with(Application::APP_ID, 'approval_group', ['admin'])
				->willReturn($approvalGroups);

			if (!empty($approvalGroups)) {
				$this->groupManager->method('getUserGroupIds')
					->willReturn($userGroups);
			}

			if ($hasPfx) {
				$this->pkcs12Handler->method('getPfxOfCurrentSigner')
					->with('testuser')
					->willReturn('signature_content');
			} else {
				$this->pkcs12Handler->method('getPfxOfCurrentSigner')
					->with('testuser')
					->willThrowException(new LibresignException('No signature file'));
			}
		}

		$service = $this->getService();
		$result = $service->getSettings($user);

		$this->assertEquals($expectedCanRequestSign, $result['canRequestSign']);
		$this->assertEquals($expectedHasSignatureFile, $result['hasSignatureFile']);
		$this->assertEquals($expectedIsApprover, $result['isApprover']);
	}
}
