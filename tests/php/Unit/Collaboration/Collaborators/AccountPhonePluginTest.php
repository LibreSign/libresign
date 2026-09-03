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

	#[DataProvider('providerSearchScenariosWithMultipleInputs')]
	public function testSearchRespectsEnumerationRulesWithMultipleInputs(
		string $method,
		array $config,
		array $currentUser,
		array $users,
		array $searchQuery,
		array $pagination,
		bool $shouldHaveMore,
		int $expectedCount,
	): void {
		$appConfig = $this->applyAppConfig($config);

		$usersByUid = array_column($users, null, 'uid');
		$userStubs = [];
		$searchedUsers = [];

		$groupsByUid = array_column($users, 'groups', 'uid');
		$groupsByUid[$currentUser['uid']] = $currentUser['groups'];

		$numberMap = array_column(
			array_column(array_merge([$currentUser], $users), 'numberMap'),
			'normalized',
			'number'
		);

		foreach ($users as $userData) {
			$uid = $userData['uid'];
			$stub = $this->createStub(IUser::class);
			$stub->method('getUID')->willReturn($uid);
			$stub->method('isEnabled')->willReturn($userData['isEnabled']);
			$stub->method('getDisplayName')->willReturn($userData['displayName']);

			$userStubs[$uid] = $stub;
			$searchedUsers[$userData['numberMap']['number']] = $uid;
		}
		$accountManager = $this->createStub(IAccountManager::class);
		$accountManager->method('searchUsers')
			->with(IAccountManager::PROPERTY_PHONE, [$searchQuery['normalized']])
			->willReturn($searchedUsers);

		$currentUserStub = $this->createStub(IUser::class);
		$currentUserStub->method('getUID')->willReturn($currentUser['uid']);
		$currentUserStub->method('getDisplayName')->willReturn($currentUser['displayName']);

		$userSession = $this->createStub(IUserSession::class);
		$userSession->method('getUser')->willReturn($currentUserStub);

		$userManager = $this->createStub(IUserManager::class);
		$userManager->method('get')
			->willReturnCallback(fn (string $uid) => (
				$uid === $currentUser['uid'] ? $currentUserStub : ($userStubs[$uid] ?? null)
			));

		$groupManager = $this->createStub(IGroupManager::class);
		$groupManager->method('getUserGroupIds')
			->willReturnCallback(fn (IUser $user) => $groupsByUid[$user->getUID()] ?? []);

		$knownUserService = $this->createStub(KnownUserService::class);
		$knownUserService->method('isKnownToUser')
			->willReturnCallback(fn (string $current, string $target) => $usersByUid[$target]['isKnown'] ?? false);

		$context = new SignerSearchContext();
		$context->set($method, $searchQuery['number'], $searchQuery['normalized']);

		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')
			->willReturnCallback(fn (string $number) => $numberMap[$number] ?? null);

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
		$hasMore = $plugin->search($searchQuery['number'], $pagination['limit'], $pagination['offset'], $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['account-phone'] ?? [], $results['exact']['account-phone'] ?? []);
		$this->assertSame($shouldHaveMore, $hasMore);
		$this->assertCount($expectedCount, $items);
	}

	public function testSearchFallsBackToUserIdWhenDisplayNameEmpty(): void {
		$appConfig = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'yes',
			'shareapi_restrict_user_enumeration_to_group' => 'no',
			'shareapi_only_share_with_group_members' => 'no',
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
		$groupManager->method('getUserGroupIds')->willReturn([]);

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
		$this->assertSame('target', $items[0]['label']);
	}

	public function testSearchAddsAccountPhoneShareType(): void {
		$appConfig = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'yes',
			'shareapi_restrict_user_enumeration_to_group' => 'no',
			'shareapi_only_share_with_group_members' => 'no',
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

	public function testSearchNonNormalizedWideMatches(): void {
		$appConfig = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'yes',
			'shareapi_restrict_user_enumeration_to_group' => 'no',
			'shareapi_only_share_with_group_members' => 'no',
		]);

		$accountManager = $this->createStub(IAccountManager::class);
		$accountManager->method('searchUsers')->willReturn(['+1 (202) 555-1234' => 'target']);

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
		$context->set('sms', '+1 (202) 555-1234', '+1 (202) 555-1234');

		$searchNormalizer = $this->createStub(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')->willReturn('+12025551234');

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
		$plugin->search('  +1 (202) 555-1234   ', 10, 0, $searchResult);

		$results = $searchResult->asArray();
		$this->assertSame([], $results['exact']['account-phone'] ?? []);
		$this->assertCount(1, $results['account-phone'] ?? []);
	}

	public function testSearchDoesNotQueryAccountsWhenEnumerationAndFullMatchDisabled(): void {
		$appConfig = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'no',
			'shareapi_restrict_user_enumeration_full_match' => 'no',
		]);

		$accountManager = $this->createMock(IAccountManager::class);
		$accountManager->expects($this->never())->method('searchUsers');

		$currentUser = $this->createStub(IUser::class);
		$currentUser->method('getUID')->willReturn('current');

		$userSession = $this->createStub(IUserSession::class);
		$userSession->method('getUser')->willReturn($currentUser);

		$context = new SignerSearchContext();
		$context->set('sms', '+12025551234', '+12025551234');

		$plugin = new AccountPhonePlugin(
			$appConfig,
			$accountManager,
			$this->createStub(IGroupManager::class),
			$userSession,
			$this->createStub(KnownUserService::class),
			$this->createStub(IUserManager::class),
			$context,
			$this->createStub(SearchNormalizer::class),
		);

		$searchResult = new SearchResult();
		$hasMore = $plugin->search('+12025551234', 10, 0, $searchResult);

		$this->assertFalse($hasMore);
		$this->assertArrayNotHasKey('account-phone', $searchResult->asArray());
	}

	public static function providerSearchScenarios(): array {
		return [
			'Email search' => [
				'method' => 'email',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
					'shareapi_restrict_user_enumeration_full_match' => 'no',
					'shareapi_restrict_user_enumeration_to_group' => 'no',
					'shareapi_restrict_user_enumeration_to_phone' => 'no',
					'shareapi_only_share_with_group_members' => 'no',
				],
				'currentUser' => [
					'uid' => 'current',
					'displayName' => 'Current User',
					'groups' => [],
					'numberMap' => ['number' => '', 'normalized' => ''],
				],
				'users' => [
					[
						'uid' => 'target',
						'displayName' => 'Target User',
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => [],
						'numberMap' => ['number' => '+12025551234', 'normalized' => '+12025551234'],
					],
				],
				'searchQuery' => ['number' => 'johnDoe@email.com', 'normalized' => ''],
				'pagination' => ['limit' => 10, 'offset' => 0],
				'shouldHaveMore' => false,
				'expectedCount' => 0,
			],
			'Test empty search' => [
				'method' => 'sms',
				'config' => [],
				'currentUser' => [
					'uid' => 'current',
					'displayName' => 'Current User',
					'groups' => [],
					'numberMap' => ['number' => '+12025551234', 'normalized' => '+12025551234'],
				],
				'users' => [
					[
						'uid' => 'excluded',
						'displayName' => 'Excluded User',
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => [],
						'numberMap' => ['number' => '+12025550003', 'normalized' => '+12025550003'],
					],
				],
				'searchQuery' => ['number' => '', 'normalized' => ''],
				'pagination' => ['limit' => 10, 'offset' => 0],
				'shouldHaveMore' => false,
				'expectedCount' => 0,
			],
			'Non trimmed search' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
					'shareapi_restrict_user_enumeration_full_match' => 'no',
					'shareapi_restrict_user_enumeration_to_group' => 'no',
					'shareapi_restrict_user_enumeration_to_phone' => 'no',
					'shareapi_only_share_with_group_members' => 'no',
				],
				'currentUser' => [
					'uid' => 'current',
					'displayName' => 'Current User',
					'groups' => [],
					'numberMap' => ['number' => '+12025550003', 'normalized' => '+12025550003'],
				],
				'users' => [
					[
						'uid' => 'target',
						'displayName' => 'Target User',
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => [],
						'numberMap' => ['number' => '+12025550003', 'normalized' => '+12025550003'],
					],
				],
				'searchQuery' => ['number' => '      +12025550003     ', 'normalized' => '+12025550003'],
				'pagination' => ['limit' => 10, 'offset' => 0],
				'shouldHaveMore' => false,
				'expectedCount' => 1,
			],
			'Apply pagination' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
					'shareapi_restrict_user_enumeration_full_match' => 'no',
					'shareapi_restrict_user_enumeration_to_group' => 'no',
					'shareapi_restrict_user_enumeration_to_phone' => 'no',
					'shareapi_only_share_with_group_members' => 'no',
				],
				'currentUser' => [
					'uid' => 'current',
					'displayName' => 'Current User',
					'groups' => [],
					'numberMap' => ['number' => '', 'normalized' => ''],
				],
				'users' => [
					[
						'uid' => 'target1',
						'displayName' => 'Target User 1',
						'numberMap' => [
							'number' => '+12025550001',
							'normalized' => '+12025550001',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => [],
					],
					[
						'uid' => 'target2',
						'displayName' => 'Target User 2',
						'numberMap' => [
							'number' => '+12025550003',
							'normalized' => '+12025550003',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => [],
					],
					[
						'uid' => 'excluded1',
						'displayName' => 'Excluded User 1',
						'numberMap' => [
							'number' => '+12025550002',
							'normalized' => '+12025550002',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => [],
					],
				],
				'searchQuery' => [
					'number' => '+12025550001',
					'normalized' => '+12025550001'
				],
				'pagination' => ['limit' => 2, 'offset' => 0],
				'shouldHaveMore' => true,
				'expectedCount' => 2,
			],
			'Edge pagination' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
					'shareapi_restrict_user_enumeration_full_match' => 'no',
					'shareapi_restrict_user_enumeration_to_group' => 'no',
					'shareapi_restrict_user_enumeration_to_phone' => 'no',
					'shareapi_only_share_with_group_members' => 'no',
				],
				'currentUser' => [
					'uid' => 'current',
					'displayName' => 'Current User',
					'groups' => [],
					'numberMap' => ['number' => '', 'normalized' => ''],
				],
				'users' => [
					[
						'uid' => 'target1',
						'displayName' => 'Target User 1',
						'numberMap' => [
							'number' => '+12025550001',
							'normalized' => '+12025550001',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => [],
					],
					[
						'uid' => 'target2',
						'displayName' => 'Target User 2',
						'numberMap' => [
							'number' => '+12025550002',
							'normalized' => '+12025550002',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => [],
					],
				],
				'searchQuery' => [
					'number' => '+12025550001',
					'normalized' => '+12025550001'
				],
				'pagination' => [
					'limit' => 2, 'offset' => 0],
				'shouldHaveMore' => false,
				'expectedCount' => 2,
			],
			'Filter disabled users' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
					'shareapi_restrict_user_enumeration_full_match' => 'no',
					'shareapi_restrict_user_enumeration_to_group' => 'no',
					'shareapi_restrict_user_enumeration_to_phone' => 'no',
					'shareapi_only_share_with_group_members' => 'no',
				],
				'currentUser' => [
					'uid' => 'current',
					'displayName' => 'Current User',
					'groups' => [],
					'numberMap' => ['number' => '', 'normalized' => ''],
				],
				'users' => [
					// The order of the elements here matters
					// So, putting the target element bettwen excluded one help us
					// to identify early returns of the search (which shouldn't happen here)
					[
						'uid' => 'target1',
						'displayName' => 'Target User 1',
						'numberMap' => [
							'number' => '+12025550001',
							'normalized' => '+12025550001',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => [],
					],
					[
						'uid' => 'excluded1',
						'displayName' => 'Excluded User 1',
						'numberMap' => [
							'number' => '+12025550003',
							'normalized' => '+12025550003',
						],
						'isEnabled' => false,
						'isKnown' => true,
						'groups' => [],
					],
					[
						'uid' => 'target2',
						'displayName' => 'Target User 2',
						'numberMap' => [
							'number' => '+12025550002',
							'normalized' => '+12025550002',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => [],
					],
				],
				'searchQuery' => [
					'number' => '+12025550001',
					'normalized' => '+12025550001'
				],
				'pagination' => ['limit' => 2, 'offset' => 0],
				'shouldHaveMore' => false,
				'expectedCount' => 2,
			],
			'Filter invalid phones' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
					'shareapi_restrict_user_enumeration_full_match' => 'no',
					'shareapi_restrict_user_enumeration_to_group' => 'no',
					'shareapi_restrict_user_enumeration_to_phone' => 'no',
					'shareapi_only_share_with_group_members' => 'no',
				],
				'currentUser' => [
					'uid' => 'current',
					'displayName' => 'Current User',
					'groups' => [],
					'numberMap' => [
						'number' => '',
						'normalized' => '',
					],
				],
				'users' => [
					// The order of the elements here matters
					// So, putting the target element bettwen excluded one help us
					// to identify early returns of the search (which shouldn't happen here)
					[
						'uid' => 'excluded1',
						'displayName' => 'Excluded User 1',
						'numberMap' => [
							'number' => '+999999999',
							'normalized' => null,
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => [],
					],
					[
						'uid' => 'target',
						'displayName' => 'Target User',
						'numberMap' => [
							'number' => '+12025550003',
							'normalized' => '+12025550003',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => [],
					],
					[
						'uid' => 'excluded2',
						'displayName' => 'Excluded User 2',
						'numberMap' => [
							'number' => '111111111',
							'normalized' => null,
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => [],
					],
				],
				'searchQuery' => [
					'number' => '+12025550003',
					'normalized' => '+12025550003'
				],
				'pagination' => [
					'limit' => 10,
					'offset' => 0,
				],
				'shouldHaveMore' => false,
				'expectedCount' => 1,
			],
			'No enumeration but full match' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'no',
					'shareapi_restrict_user_enumeration_full_match' => 'yes',
					'shareapi_only_share_with_group_members' => 'yes',
					'shareapi_only_share_with_group_members_exclude_group_list' => [],
				],
				'currentUser' => [
					'uid' => 'current',
					'displayName' => 'Current User',
					'groups' => ['sales'],
					'numberMap' => [
						'number' => '',
						'normalized' => '',
					],
				],
				'users' => [
					[
						'uid' => 'target1',
						'displayName' => 'Target User 1',
						'numberMap' => [
							'number' => '+12025550002',
							'normalized' => '+12025550002',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => ['sales'],
					],
					[
						'uid' => 'target2',
						'displayName' => 'Target User 2',
						'numberMap' => [
							'number' => '+12025550003',
							'normalized' => '+12025550003',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => ['sales'],
					],
				],
				'searchQuery' => [
					'number' => '+12025550001',
					'normalized' => '+12025550001'
				],
				'pagination' => [
					'limit' => 10,
					'offset' => 0,
				],
				'shouldHaveMore' => false,
				'expectedCount' => 2,
			],
			'Filter unknown phones' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
					'shareapi_restrict_user_enumeration_full_match' => 'no',
					'shareapi_restrict_user_enumeration_to_group' => 'no',
					'shareapi_restrict_user_enumeration_to_phone' => 'yes',
					'shareapi_only_share_with_group_members' => 'no',
					'shareapi_only_share_with_group_members_exclude_group_list' => [],
				],
				'currentUser' => [
					'uid' => 'current',
					'displayName' => 'Current User',
					'groups' => ['sales'],
					'numberMap' => [
						'number' => '',
						'normalized' => '',
					],
				],
				'users' => [
					[
						'uid' => 'excluded1',
						'displayName' => 'Excluded User 1',
						'numberMap' => [
							'number' => '+12025550002',
							'normalized' => '+12025550002',
						],
						'isEnabled' => true,
						'isKnown' => false,
						'groups' => ['sales'],
					],
					[
						'uid' => 'target',
						'displayName' => 'Target User',
						'numberMap' => [
							'number' => '+12025550003',
							'normalized' => '+12025550003',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => [],
					],
					[
						'uid' => 'excluded2',
						'displayName' => 'Excluded User 2',
						'numberMap' => [
							'number' => '+12025550004',
							'normalized' => '+12025550004',
						],
						'isEnabled' => true,
						'isKnown' => false,
						'groups' => [],
					],
				],
				'searchQuery' => [
					'number' => '+12025550003',
					'normalized' => '+12025550003'
				],
				'pagination' => [
					'limit' => 10,
					'offset' => 0,
				],
				'shouldHaveMore' => false,
				'expectedCount' => 1,
			],
			'Filter phones and restrict by group' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
					'shareapi_restrict_user_enumeration_full_match' => 'no',
					'shareapi_restrict_user_enumeration_to_group' => 'yes',
					'shareapi_restrict_user_enumeration_to_phone' => 'yes',
					'shareapi_only_share_with_group_members' => 'no',
					'shareapi_only_share_with_group_members_exclude_group_list' => [],
				],
				'currentUser' => [
					'uid' => 'current',
					'displayName' => 'Current User',
					'groups' => ['sales'],
					'numberMap' => [
						'number' => '',
						'normalized' => '',
					],
				],
				'users' => [
					[
						'uid' => 'excluded1',
						'displayName' => 'Excluded User 1',
						'numberMap' => [
							'number' => '+12025550002',
							'normalized' => '+12025550002',
						],
						'isEnabled' => true,
						'isKnown' => false,
						'groups' => [],
					],
					[
						'uid' => 'target',
						'displayName' => 'Target User 1',
						'numberMap' => [
							'number' => '+12025550003',
							'normalized' => '+12025550003',
						],
						'isEnabled' => true,
						'isKnown' => false,
						'groups' => ['sales'],
					],
					[
						'uid' => 'excluded2',
						'displayName' => 'Excluded User 2',
						'numberMap' => [
							'number' => '+12025550004',
							'normalized' => '+12025550004',
						],
						'isEnabled' => true,
						'isKnown' => false,
						'groups' => [],
					],
					[
						'uid' => 'target',
						'displayName' => 'Target User 2',
						'numberMap' => [
							'number' => '+12025550004',
							'normalized' => '+12025550004',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => [],
					],
				],
				'searchQuery' => [
					'number' => '+12025550003',
					'normalized' => '+12025550003'
				],
				'pagination' => [
					'limit' => 10,
					'offset' => 0,
				],
				'shouldHaveMore' => false,
				'expectedCount' => 2,
			],
			'Filter disallowed groups' => [
				'method' => 'sms',
				'config' => [
					'shareapi_only_share_with_group_members' => 'yes',
					'shareapi_only_share_with_group_members_exclude_group_list' => ['engineering'],
				],
				'currentUser' => [
					'uid' => 'current',
					'displayName' => 'Current User',
					'groups' => ['sales'],
					'numberMap' => ['number' => '', 'normalized' => ''],
				],
				'users' => [
					[
						'uid' => 'excluded1',
						'displayName' => 'Excluded User 1',
						'numberMap' => [
							'number' => '+1202555001',
							'normalized' => '+1202555001',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => ['engineering'],
					],
					[
						'uid' => 'target1',
						'displayName' => 'Target User 1',
						'numberMap' => [
							'number' => '+1202555002',
							'normalized' => '+1202555002',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => ['sales', 'engineering'],
					],
					[
						'uid' => 'excluded2',
						'displayName' => 'Excluded User 2',
						'numberMap' => [
							'number' => '+1202555003',
							'normalized' => '+1202555003',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => ['engineering'],
					],
					[
						'uid' => 'target2',
						'displayName' => 'Target User 2',
						'numberMap' => [
							'number' => '+1202555004',
							'normalized' => '+1202555004',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => ['sales'],
					],
				],
				'searchQuery' => [
					'number' => '+1202555001',
					'normalized' => '+1202555001',
				],
				'pagination' => ['limit' => 10, 'offset' => 0],
				'shouldHaveMore' => false,
				'expectedCount' => 2,
			],
			'Filter group-only users' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'no',
					'shareapi_only_share_with_group_members' => 'yes',
					'shareapi_restrict_user_enumeration_to_phone' => 'no',
					'shareapi_restrict_user_enumeration_to_group' => 'yes',
					'shareapi_restrict_user_enumeration_full_match' => 'yes',
					'shareapi_only_share_with_group_members_exclude_group_list' => [],
				],
				'currentUser' => [
					'uid' => 'current',
					'displayName' => 'Current User',
					'groups' => ['sales'],
					'numberMap' => [
						'number' => '',
						'normalized' => '',
					],
				],
				'users' => [
					[
						'uid' => 'target1',
						'displayName' => 'Target User 1',
						'numberMap' => ['number' => '+12025550001', 'normalized' => '+12025550001'],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => ['sales'],
					],
					[
						'uid' => 'excluded1',
						'displayName' => 'Excluded User 1',
						'numberMap' => ['number' => '+12025550002', 'normalized' => '+12025550002'],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => ['engineering'],
					],
					[
						'uid' => 'target2',
						'displayName' => 'Target User 2',
						'numberMap' => ['number' => '+12025550003', 'normalized' => '+12025550003'],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => ['sales'],
					],
				],
				'searchQuery' => [
					'number' => '+12025550000',
					'normalized' => '+12025550000',
				],
				'pagination' => [
					'limit' => 10,
					'offset' => 0,
				],
				'shouldHaveMore' => false,
				'expectedCount' => 2,
			],
			'Restrict and exclude own group' => [
				'method' => 'sms',
				'config' => [
					'shareapi_restrict_user_enumeration_to_group' => 'yes',
					'shareapi_only_share_with_group_members' => 'yes',
					'shareapi_only_share_with_group_members_exclude_group_list' => ['sales'],
				],
				'currentUser' => [
					'uid' => 'current',
					'displayName' => 'Current User',
					'groups' => ['sales'],
					'numberMap' => ['number' => '', 'normalized' => ''],
				],
				'users' => [
					[
						'uid' => 'excluded1',
						'displayName' => 'Excluded User 1',
						'numberMap' => [
							'number' => '+12025550002',
							'normalized' => '+12025550002',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => ['sales'],
					],
					[
						'uid' => 'excluded2',
						'displayName' => 'Excluded User 2',
						'numberMap' => [
							'number' => '+12025550004',
							'normalized' => '+12025550004',
						],
						'isEnabled' => true,
						'isKnown' => true,
						'groups' => ['sales'],
					],
				],
				'searchQuery' => [
					'number' => '+12025550003',
					'normalized' => '+12025550003'
				],
				'pagination' => ['limit' => 10, 'offset' => 0],
				'shouldHaveMore' => false,
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
