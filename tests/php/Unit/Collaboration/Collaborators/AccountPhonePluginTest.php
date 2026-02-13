<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Collaboration\Collaborators;

use OC\Collaboration\Collaborators\SearchResult;
use OC\KnownUser\KnownUserService;
use OCA\Libresign\Collaboration\Collaborators\AccountPhonePlugin;
use OCA\Libresign\Service\Identify\SearchNormalizer;
use OCA\Libresign\Service\Identify\SignerSearchContext;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\Accounts\IAccountManager;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;

class AccountPhonePluginTest extends TestCase {
	#[DataProvider('providerSearchScenarios')]
	public function testSearchRespectsEnumerationRules(
		string $method,
		array $config,
		bool $knownUser,
		array $currentGroups,
		array $targetGroups,
		bool $userEnabled,
		int $expectedCount,
	): void {
		$appConfig = $this->applyAppConfig($config);

		$accountManager = $this->createStub(IAccountManager::class);
		$accountManager->method('searchUsers')
			->with(IAccountManager::PROPERTY_PHONE, ['+12025551234'])
			->willReturn(['+12025551234' => 'target']);

		$currentUser = $this->createStub(IUser::class);
		$currentUser->method('getUID')->willReturn('current');

		$userSession = $this->createStub(IUserSession::class);
		$userSession->method('getUser')->willReturn($currentUser);

		$user = $this->createStub(IUser::class);
		$user->method('getUID')->willReturn('target');
		$user->method('isEnabled')->willReturn($userEnabled);
		$user->method('getDisplayName')->willReturn('Target User');

		$userManager = $this->createStub(IUserManager::class);
		$userManager->method('get')
			->willReturnCallback(function (string $uid) use ($user, $currentUser) {
				return $uid === 'target' ? $user : ($uid === 'current' ? $currentUser : null);
			});

		$groupManager = $this->createStub(IGroupManager::class);
		$groupManager->method('getUserGroupIds')
			->willReturnCallback(function ($subject) use ($currentUser, $user, $currentGroups, $targetGroups): array {
				if ($subject === $currentUser) {
					return $currentGroups;
				}
				if ($subject === $user || $subject === 'target') {
					return $targetGroups;
				}
				return [];
			});

		$knownUserService = $this->createStub(KnownUserService::class);
		$knownUserService->method('isKnownToUser')
			->with('current', 'target')
			->willReturn($knownUser);

		$context = new SignerSearchContext();
		$context->set($method, '+12025551234', '+12025551234');

		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')
			->willReturn('+12025551234');

		$plugin = new AccountPhonePlugin(
			$appConfig,
			$accountManager,
			$groupManager,
			$userSession,
			$knownUserService,
			$userManager,
			$context,
			$searchNormalizer,
		);

		$searchResult = new SearchResult();
		$plugin->search('+12025551234', 10, 0, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['account-phone'] ?? [], $results['exact']['account-phone'] ?? []);
		$this->assertCount($expectedCount, $items);
	}

	public function testSearchAppliesPagination(): void {
		$appConfig = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'yes',
		]);

		$accountManager = $this->createStub(IAccountManager::class);
		$accountManager->method('searchUsers')
			->willReturn([
				'+12025550001' => 'target1',
				'+12025550002' => 'target2',
				'+12025550003' => 'target3',
			]);

		$currentUser = $this->createStub(IUser::class);
		$currentUser->method('getUID')->willReturn('current');

		$userSession = $this->createStub(IUserSession::class);
		$userSession->method('getUser')->willReturn($currentUser);

		$user = $this->createStub(IUser::class);
		$user->method('isEnabled')->willReturn(true);
		$user->method('getDisplayName')->willReturn('Target User');

		$userManager = $this->createStub(IUserManager::class);
		$userManager->method('get')->willReturn($user);

		$groupManager = $this->createStub(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['sales']);

		$knownUserService = $this->createStub(KnownUserService::class);
		$knownUserService->method('isKnownToUser')->willReturn(true);

		$context = new SignerSearchContext();
		$context->set('sms', '+12025550001', '+12025550001');

		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')
			->willReturn('+12025550001');

		$plugin = new AccountPhonePlugin(
			$appConfig,
			$accountManager,
			$groupManager,
			$userSession,
			$knownUserService,
			$userManager,
			$context,
			$searchNormalizer,
		);

		$searchResult = new SearchResult();
		$hasMore = $plugin->search('+12025550001', 1, 1, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['account-phone'] ?? [], $results['exact']['account-phone'] ?? []);
		$this->assertCount(1, $items);
		$this->assertTrue($hasMore);
	}

	public function testSearchFallsBackToUserIdWhenDisplayNameEmpty(): void {
		$appConfig = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'yes',
		]);

		$accountManager = $this->createStub(IAccountManager::class);
		$accountManager->method('searchUsers')
			->willReturn(['+12025551234' => 'target']);

		$currentUser = $this->createStub(IUser::class);
		$currentUser->method('getUID')->willReturn('current');

		$userSession = $this->createStub(IUserSession::class);
		$userSession->method('getUser')->willReturn($currentUser);

		$user = $this->createStub(IUser::class);
		$user->method('getUID')->willReturn('target');
		$user->method('isEnabled')->willReturn(true);
		$user->method('getDisplayName')->willReturn('');

		$userManager = $this->createStub(IUserManager::class);
		$userManager->method('get')->willReturn($user);

		$groupManager = $this->createStub(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['sales']);

		$knownUserService = $this->createStub(KnownUserService::class);
		$knownUserService->method('isKnownToUser')->willReturn(true);

		$context = new SignerSearchContext();
		$context->set('sms', '+12025551234', '+12025551234');

		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')
			->willReturn('+12025550001');

		$plugin = new AccountPhonePlugin(
			$appConfig,
			$accountManager,
			$groupManager,
			$userSession,
			$knownUserService,
			$userManager,
			$context,
			$searchNormalizer,
		);

		$searchResult = new SearchResult();
		$plugin->search('+12025551234', 10, 0, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['account-phone'] ?? [], $results['exact']['account-phone'] ?? []);
		$this->assertSame('target', $items[0]['label']);
	}

	public function testSearchAddsAccountPhoneShareType(): void {
		$appConfig = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'yes',
		]);

		$accountManager = $this->createStub(IAccountManager::class);
		$accountManager->method('searchUsers')
			->willReturn(['+12025551234' => 'target']);

		$currentUser = $this->createStub(IUser::class);
		$currentUser->method('getUID')->willReturn('current');

		$userSession = $this->createStub(IUserSession::class);
		$userSession->method('getUser')->willReturn($currentUser);

		$user = $this->createStub(IUser::class);
		$user->method('getUID')->willReturn('target');
		$user->method('isEnabled')->willReturn(true);
		$user->method('getDisplayName')->willReturn('Target User');

		$userManager = $this->createStub(IUserManager::class);
		$userManager->method('get')->willReturn($user);

		$groupManager = $this->createStub(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['sales']);

		$knownUserService = $this->createStub(KnownUserService::class);
		$knownUserService->method('isKnownToUser')->willReturn(true);

		$context = new SignerSearchContext();
		$context->set('sms', '+12025551234', '+12025551234');

		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')
			->willReturn('+12025551234');

		$plugin = new AccountPhonePlugin(
			$appConfig,
			$accountManager,
			$groupManager,
			$userSession,
			$knownUserService,
			$userManager,
			$context,
			$searchNormalizer,
		);

		$searchResult = new SearchResult();
		$plugin->search('+12025551234', 10, 0, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['account-phone'] ?? [], $results['exact']['account-phone'] ?? []);
		$this->assertSame(AccountPhonePlugin::TYPE_SIGNER_ACCOUNT_PHONE, $items[0]['value']['shareType']);
	}

	public function testSearchFiltersUsersWithInvalidPhoneNumbers(): void {
		$appConfig = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'yes',
		]);

		// Return user with phone number that cannot be normalized
		$accountManager = $this->createStub(IAccountManager::class);
		$accountManager->method('searchUsers')
			->willReturn(['999999999' => 'target']);

		$currentUser = $this->createStub(IUser::class);
		$currentUser->method('getUID')->willReturn('current');

		$userSession = $this->createStub(IUserSession::class);
		$userSession->method('getUser')->willReturn($currentUser);

		$user = $this->createStub(IUser::class);
		$user->method('getUID')->willReturn('target');
		$user->method('isEnabled')->willReturn(true);
		$user->method('getDisplayName')->willReturn('Target User');

		$userManager = $this->createStub(IUserManager::class);
		$userManager->method('get')->willReturn($user);

		$groupManager = $this->createStub(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['sales']);

		$knownUserService = $this->createStub(KnownUserService::class);
		$knownUserService->method('isKnownToUser')->willReturn(true);

		$context = new SignerSearchContext();
		$context->set('sms', '999999999', '999999999');

		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')
			->with('999999999', 'sms')
			->willReturn(null); // Cannot normalize

		$plugin = new AccountPhonePlugin(
			$appConfig,
			$accountManager,
			$groupManager,
			$userSession,
			$knownUserService,
			$userManager,
			$context,
			$searchNormalizer,
		);

		$searchResult = new SearchResult();
		$plugin->search('999999999', 10, 0, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['account-phone'] ?? [], $results['exact']['account-phone'] ?? []);
		$this->assertCount(0, $items); // User should be filtered out
	}

	public static function providerSearchScenarios(): array {
		return [
			'non phone method' => [
				'method' => 'email',
				'config' => [],
				'knownUser' => false,
				'currentGroups' => ['sales'],
				'targetGroups' => ['sales'],
				'userEnabled' => true,
				'expectedCount' => 0,
			],
			'enumeration disabled and no full match' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'no',
					'shareapi_restrict_user_enumeration_full_match' => 'no',
				],
				'knownUser' => true,
				'currentGroups' => ['sales'],
				'targetGroups' => ['sales'],
				'userEnabled' => true,
				'expectedCount' => 0,
			],
			'enumeration allowed without restrictions' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
				],
				'knownUser' => false,
				'currentGroups' => ['sales'],
				'targetGroups' => ['engineering'],
				'userEnabled' => true,
				'expectedCount' => 1,
			],
			'restrict to group without common group' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
					'shareapi_restrict_user_enumeration_to_group' => 'yes',
				],
				'knownUser' => false,
				'currentGroups' => ['sales'],
				'targetGroups' => ['engineering'],
				'userEnabled' => true,
				'expectedCount' => 0,
			],
			'restrict to group with common group' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
					'shareapi_restrict_user_enumeration_to_group' => 'yes',
				],
				'knownUser' => false,
				'currentGroups' => ['sales'],
				'targetGroups' => ['sales'],
				'userEnabled' => true,
				'expectedCount' => 1,
			],
			'restrict to phone not known' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
					'shareapi_restrict_user_enumeration_to_phone' => 'yes',
				],
				'knownUser' => false,
				'currentGroups' => ['sales'],
				'targetGroups' => ['sales'],
				'userEnabled' => true,
				'expectedCount' => 0,
			],
			'share with group only without common group' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
					'shareapi_only_share_with_group_members' => 'yes',
				],
				'knownUser' => true,
				'currentGroups' => ['sales'],
				'targetGroups' => ['engineering'],
				'userEnabled' => true,
				'expectedCount' => 0,
			],
			'disabled user filtered' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
				],
				'knownUser' => true,
				'currentGroups' => ['sales'],
				'targetGroups' => ['sales'],
				'userEnabled' => false,
				'expectedCount' => 0,
			],
			'exclude group list removes allowed groups' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
					'shareapi_only_share_with_group_members' => 'yes',
					'shareapi_only_share_with_group_members_exclude_group_list' => ['sales'],
				],
				'knownUser' => true,
				'currentGroups' => ['sales'],
				'targetGroups' => ['sales'],
				'userEnabled' => true,
				'expectedCount' => 0,
			],
		];
	}

	private function applyAppConfig(array $config) {
		$appConfig = $this->getMockAppConfigWithReset();
		foreach ($config as $key => $value) {
			if (is_array($value)) {
				$value = json_encode($value);
			}
			$appConfig->setValueString('core', $key, (string)$value);
		}
		return $appConfig;
	}
}
