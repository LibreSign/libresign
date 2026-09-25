<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Collaboration\Collaborators;

use OC\Collaboration\Collaborators\SearchResult;
use OC\KnownUser\KnownUserService;
use OCA\Libresign\Collaboration\Collaborators\ContactPhonePlugin;
use OCA\Libresign\Service\Identify\SearchNormalizer;
use OCA\Libresign\Service\Identify\SignerSearchContext;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\Contacts\IManager;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;

class ContactPhonePluginTest extends TestCase {
	#[DataProvider('providerSearchScenarios')]
	public function testSearchRespectsEnumerationRules(
		string $method,
		array $config,
		bool $knownUser,
		array $currentGroups,
		array $contactGroups,
		bool $isSystemBook,
		int $expectedCount,
		bool $expectedHasMore = false,
	): void {
		$appConfig = $this->applyAppConfig($config);

		$currentUser = $this->createMock(IUser::class);
		$currentUser->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($currentUser);

		$contactUser = $this->createMock(IUser::class);
		$contactUser->method('getUID')->willReturn('contactUser');

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')
			->willReturnCallback(fn (string $uid): ?IUser => $uid === 'contactUser' ? $contactUser : null);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')
			->willReturnCallback(function ($subject) use ($currentUser, $contactUser, $currentGroups, $contactGroups): array {
				if ($subject === $currentUser) {
					return $currentGroups;
				}
				if ($subject === $contactUser) {
					return $contactGroups;
				}
				return [];
			});

		$knownUserService = $this->createMock(KnownUserService::class);
		$knownUserService->method('isKnownToUser')
			->with('current', 'contactUser')
			->willReturn($knownUser);

		$contactsManager = $this->createMock(IManager::class);
		$contactsManager->method('isEnabled')->willReturn(true);
		$contactsManager->method('search')
			->with(
				'+12025551234',
				['TEL', 'FN'],
				$this->callback(function (array $options) {
					return $options['limit'] === 11 // offset(0) + limit(10) + 1
						&& $options['offset'] === 0
						&& $options['types'] === true;
				})
			)
			->willReturn([
				array_filter([
					'FN' => 'Contact Name',
					'UID' => 'contactUser',
					'isLocalSystemBook' => $isSystemBook ? true : null,
					'TEL' => [
						['value' => '+12025551234'],
					],
				]),
			]);

		$context = new SignerSearchContext();
		$context->set($method, '+12025551234', '+12025551234');

		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')
			->willReturnCallback(fn (string $input) => $input);

		$plugin = new ContactPhonePlugin(
			$appConfig,
			$contactsManager,
			$groupManager,
			$userManager,
			$userSession,
			$knownUserService,
			$context,
			$searchNormalizer,
		);

		$searchResult = new SearchResult();
		$hasMore = $plugin->search('+12025551234', 10, 0, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['contact-phone'] ?? [], $results['exact']['contact-phone'] ?? []);
		$this->assertCount($expectedCount, $items);
		$this->assertSame($expectedHasMore, $hasMore);
	}

	public function testSearchSkipsSystemContactWithoutUid(): void {
		$appConfig = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'yes',
			'shareapi_only_share_with_group_members_exclude_group_list' => [],
		]);

		$currentUser = $this->createMock(IUser::class);
		$currentUser->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($currentUser);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturn($currentUser);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['sales']);

		$knownUserService = $this->createMock(KnownUserService::class);
		$knownUserService->method('isKnownToUser')->willReturn(true);

		$contactsManager = $this->createMock(IManager::class);
		$contactsManager->method('isEnabled')->willReturn(true);
		$contactsManager->method('search')
			->willReturn([
				[
					'FN' => 'Filtered Contact Name',
					'UID' => '',
					'isLocalSystemBook' => true,
					'TEL' => [
						['value' => '+12025551234'],
					],
				],
				[
					'FN' => 'Contact Name',
					'isLocalSystemBook' => false,
					'TEL' => [
						['value' => '+12025551235'],
					],
				],
			]);

		$context = new SignerSearchContext();
		$context->set('sms', '+12025551234', '+12025551234');

		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')
			->willReturnCallback(fn (string $input) => $input);

		$plugin = new ContactPhonePlugin(
			$appConfig,
			$contactsManager,
			$groupManager,
			$userManager,
			$userSession,
			$knownUserService,
			$context,
			$searchNormalizer,
		);

		$searchResult = new SearchResult();
		$hasMore = $plugin->search('+12025551234', 10, 0, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['contact-phone'] ?? [], $results['exact']['contact-phone'] ?? []);
		$this->assertCount(1, $items);
		$this->assertFalse($hasMore);
	}

	public function testSearchAppliesPagination(): void {
		$appConfig = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'yes',
			'shareapi_only_share_with_group_members_exclude_group_list' => [],
		]);

		$currentUser = $this->createMock(IUser::class);
		$currentUser->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($currentUser);

		$contactUser1 = $this->createMock(IUser::class);
		$contactUser1->method('getUID')->willReturn('contactUser1');

		$contactUser2 = $this->createMock(IUser::class);
		$contactUser2->method('getUID')->willReturn('contactUser2');

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')
			->willReturnCallback(function (string $uid) use ($currentUser, $contactUser1, $contactUser2) {
				if ($uid === 'current') {
					return $currentUser;
				}
				if ($uid === 'contactUser1') {
					return $contactUser1;
				}
				if ($uid === 'contactUser2') {
					return $contactUser2;
				}
				return null;
			});

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['sales']);

		$knownUserService = $this->createMock(KnownUserService::class);
		$knownUserService->method('isKnownToUser')->willReturn(true);

		$contactsManager = $this->createMock(IManager::class);
		$contactsManager->method('isEnabled')->willReturn(true);
		$contactsManager->method('search')->willReturn([
			[
				'UID' => 'contactUser1',
				'isLocalSystemBook' => true,
				'TEL' => [
					['value' => '+12025550001'],
					['value' => '+12025550002'],
				],
			],
			[
				'UID' => 'contactUser2',
				'isLocalSystemBook' => true,
				'TEL' => [
					['value' => '+12025550003'],
				],
			],
		]);

		$context = new SignerSearchContext();
		$context->set('sms', '+12025550001', '+12025550001');
		$context->set('sms', '+12025550002', '+12025550002');
		$context->set('sms', '+12025550003', '+12025550003');

		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')
			->willReturnCallback(fn (string $input) => $input);

		$plugin = new ContactPhonePlugin(
			$appConfig,
			$contactsManager,
			$groupManager,
			$userManager,
			$userSession,
			$knownUserService,
			$context,
			$searchNormalizer,
		);

		$searchResult = new SearchResult();
		$hasMore = $plugin->search('202555', 2, 0, $searchResult);
		$results = $searchResult->asArray();
		$items = array_merge($results['contact-phone'] ?? [], $results['exact']['contact-phone'] ?? []);
		$this->assertCount(2, $items);
		$this->assertTrue($hasMore);

		$searchResult = new SearchResult();
		$hasMore = $plugin->search('202555', 2, 1, $searchResult);
		$results = $searchResult->asArray();
		$items = array_merge($results['contact-phone'] ?? [], $results['exact']['contact-phone'] ?? []);
		$this->assertCount(2, $items);
		$this->assertFalse($hasMore);
	}

	public function testMaxLimitStopsProcessingImmediately(): void {
		$appConfig = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'yes',
		]);

		$currentUser = $this->createMock(IUser::class);
		$currentUser->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($currentUser);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturn(null);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn([]);

		$knownUserService = $this->createMock(KnownUserService::class);
		$knownUserService->method('isKnownToUser')->willReturn(true);

		$contactsManager = $this->createMock(IManager::class);
		$contactsManager->method('isEnabled')->willReturn(true);
		$contactsManager->method('search')->willReturn([
			[
				'FN' => 'Contact One',
				'isLocalSystemBook' => false,
				'TEL' => [
					['value' => '+1'],
					['value' => '+2'],
					['value' => '+3'],
				],
			],
			[
				'FN' => 'Contact Two',
				'isLocalSystemBook' => false,
				'TEL' => [
					['value' => '+4'],
				],
			],
		]);

		$context = new SignerSearchContext();
		$context->set('sms', 'x', 'x');

		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->expects($this->exactly(2))
			->method('tryNormalizePhoneNumber')
			->willReturnCallback(fn (string $input) => $input);

		$plugin = new ContactPhonePlugin(
			$appConfig,
			$contactsManager,
			$groupManager,
			$userManager,
			$userSession,
			$knownUserService,
			$context,
			$searchNormalizer,
		);

		$searchResult = new SearchResult();
		$plugin->search('x', 1, 0, $searchResult);
	}

	public function testSearchDistinguishesMatchesCaseInsensitively(): void {
		$appConfig = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'yes',
		]);

		$currentUser = $this->createMock(IUser::class);
		$currentUser->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($currentUser);

		$contactUser1 = $this->createMock(IUser::class);
		$contactUser1->method('getUID')->willReturn('contactUser1');

		$contactUser2 = $this->createMock(IUser::class);
		$contactUser2->method('getUID')->willReturn('contactUser2');

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')
			->willReturnCallback(function (string $uid) use ($currentUser, $contactUser1, $contactUser2) {
				if ($uid === 'current') {
					return $currentUser;
				}
				if ($uid === 'contactUser1') {
					return $contactUser1;
				}
				if ($uid === 'contactUser2') {
					return $contactUser2;
				}
				return null;
			});

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['sales']);

		$knownUserService = $this->createMock(KnownUserService::class);
		$knownUserService->method('isKnownToUser')->willReturn(true);

		$contactsManager = $this->createMock(IManager::class);
		$contactsManager->method('isEnabled')->willReturn(true);
		$contactsManager->method('search')->willReturn([
			[
				'FN' => 'Contact User',
				'UID' => 'contactUser1',
				'isLocalSystemBook' => true,
				'TEL' => [['value' => '+12025550001']],
			],
			[
				'FN' => 'Contact User Extra',
				'UID' => 'contactUser2',
				'isLocalSystemBook' => true,
				'TEL' => [['value' => '+12025550002']],
			],
		]);

		$context = new SignerSearchContext();
		$context->set('sms', '+12025550001', '+12025550001');
		$context->set('sms', '+12025550002', '+12025550002');

		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')
			->willReturnCallback(fn (string $input) => $input);

		$plugin = new ContactPhonePlugin(
			$appConfig,
			$contactsManager,
			$groupManager,
			$userManager,
			$userSession,
			$knownUserService,
			$context,
			$searchNormalizer,
		);

		$searchResult = new SearchResult();
		$plugin->search('cOnTaCt uSeR', 10, 0, $searchResult);
		$results = $searchResult->asArray();

		$exactItems = $results['exact']['contact-phone'] ?? [];
		$wideItems = $results['contact-phone'] ?? [];

		$this->assertCount(1, $exactItems);
		$this->assertSame('Contact User', $exactItems[0]['label']);

		$this->assertCount(1, $wideItems);
		$this->assertSame('Contact User Extra', $wideItems[0]['label']);
	}

	public function testSearchAddsContactPhoneShareType(): void {
		$appConfig = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'yes',
		]);

		$currentUser = $this->createMock(IUser::class);
		$currentUser->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($currentUser);

		$contactUser = $this->createMock(IUser::class);
		$contactUser->method('getUID')->willReturn('contactUser');

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')
			->willReturnCallback(fn (string $uid): ?IUser => $uid === 'contactUser' ? $contactUser : null);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')
			->willReturnCallback(function ($subject) use ($currentUser, $contactUser): array {
				if ($subject === $currentUser || $subject === $contactUser) {
					return ['sales'];
				}
				return [];
			});

		$knownUserService = $this->createMock(KnownUserService::class);
		$knownUserService->method('isKnownToUser')->willReturn(true);

		$contactsManager = $this->createMock(IManager::class);
		$contactsManager->method('isEnabled')->willReturn(true);
		$contactsManager->method('search')
			->willReturn([[
				'FN' => 'Contact Name',
				'UID' => 'contactUser',
				'isLocalSystemBook' => true,
				'TEL' => [
					['value' => '+12025551234'],
				],
			]]);

		$context = new SignerSearchContext();
		$context->set('sms', '+12025551234', '+12025551234');

		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')
			->willReturnCallback(fn (string $input) => $input);

		$plugin = new ContactPhonePlugin(
			$appConfig,
			$contactsManager,
			$groupManager,
			$userManager,
			$userSession,
			$knownUserService,
			$context,
			$searchNormalizer,
		);

		$searchResult = new SearchResult();
		$hasMore = $plugin->search('+12025551234', 10, 0, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['contact-phone'] ?? [], $results['exact']['contact-phone'] ?? []);
		$this->assertSame(ContactPhonePlugin::TYPE_SIGNER_CONTACT_PHONE, $items[0]['value']['shareType']);
		$this->assertFalse($hasMore);
	}

	public function testSearchFiltersContactsWithInvalidPhoneNumbers(): void {
		$appConfig = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'yes',
		]);

		$currentUser = $this->createMock(IUser::class);
		$currentUser->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($currentUser);

		$contactUser = $this->createMock(IUser::class);
		$contactUser->method('getUID')->willReturn('contactUser');

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturn($contactUser);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['sales']);

		$knownUserService = $this->createMock(KnownUserService::class);
		$knownUserService->method('isKnownToUser')->willReturn(true);

		$contactsManager = $this->createMock(IManager::class);
		$contactsManager->method('isEnabled')->willReturn(true);

		$contactsManager->method('search')
			->willReturn([
				[
					'FN' => 'Contact Name 1',
					'UID' => 'contactUser1',
					'isLocalSystemBook' => true,
					'TEL' => [
						'value' => '+12025551234',
						// The code should not retrieve this field
						'custom' => '+12025559999',
					],
				],
				[
					'FN' => 'Contact Name 2',
					'UID' => 'contactUser2',
					'isLocalSystemBook' => true,
					'TEL' => [
						['value' => ''],
						['value' => 12025551235],
						['value' => '+12025551235'],
						['value' => '999999999'],
					],
				],
			]);

		$context = new SignerSearchContext();
		$context->set('sms', '+12025551234', '+12025551234');
		$context->set('sms', '+12025551235', '+12025551235');
		$context->set('sms', '+12025559999', '+12025559999');
		$context->set('sms', '999999999', '');
		$context->set('sms', '', '');

		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')
			->willReturnCallback(function (string $input) {
				if ($input === '+12025551234' || $input === '+12025551235' || $input === '+12025559999' || $input === '') {
					return $input;
				}

				return null;
			});

		$plugin = new ContactPhonePlugin(
			$appConfig,
			$contactsManager,
			$groupManager,
			$userManager,
			$userSession,
			$knownUserService,
			$context,
			$searchNormalizer,
		);

		$searchResult = new SearchResult();
		$hasMore = $plugin->search('1202555123', 10, 0, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['contact-phone'] ?? [], $results['exact']['contact-phone'] ?? []);

		$this->assertCount(2, $items);
		$this->assertFalse($hasMore);
	}

	public function testEarlyReturnWhenInvalidSearchQuery(): void {
		$appConfig = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'yes',
		]);

		$currentUser = $this->createMock(IUser::class);
		$currentUser->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($currentUser);

		$contactUser = $this->createMock(IUser::class);
		$contactUser->method('getUID')->willReturn('contactUser');

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturn($contactUser);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['sales']);

		$knownUserService = $this->createMock(KnownUserService::class);
		$knownUserService->method('isKnownToUser')->willReturn(true);

		$contactsManager = $this->createMock(IManager::class);
		$contactsManager->method('isEnabled')->willReturn(true);
		$contactsManager->method('search')
			->willReturn([[
				'FN' => 'Contact Name',
				'UID' => 'contactUser',
				'isLocalSystemBook' => true,
				'TEL' => [
					['value' => '+12025551234'],
				],
			]]);

		$context = new SignerSearchContext();
		$context->set('sms', '+12025551234', '+12025551234');

		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')
			->with('+12025551234', 'sms')
			->willReturn('+12025551234');

		$plugin = new ContactPhonePlugin(
			$appConfig,
			$contactsManager,
			$groupManager,
			$userManager,
			$userSession,
			$knownUserService,
			$context,
			$searchNormalizer,
		);

		$searchResult = new SearchResult();
		// Should return early
		$hasMore = $plugin->search('', 10, 0, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['contact-phone'] ?? [], $results['exact']['contact-phone'] ?? []);
		$this->assertCount(0, $items);
		$this->assertFalse($hasMore);

		// Search consisting only of whitespace should behave like empty search
		$searchResult = new SearchResult();
		$hasMore = $plugin->search('   ', 10, 0, $searchResult);
		$results = $searchResult->asArray();
		$items = array_merge($results['contact-phone'] ?? [], $results['exact']['contact-phone'] ?? []);
		$this->assertCount(0, $items);
		$this->assertFalse($hasMore);
	}

	public function testFallbackToPhoneWhenNoFN(): void {
		$appConfig = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'yes',
		]);

		$currentUser = $this->createMock(IUser::class);
		$currentUser->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($currentUser);

		$contactUser = $this->createMock(IUser::class);
		$contactUser->method('getUID')->willReturn('contactUser');

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturn($contactUser);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['sales']);

		$knownUserService = $this->createMock(KnownUserService::class);
		$knownUserService->method('isKnownToUser')->willReturn(true);

		$contactsManager = $this->createMock(IManager::class);
		$contactsManager->method('isEnabled')->willReturn(true);
		$contactsManager->method('search')
			->willReturn([[
				'FN' => '',
				'UID' => 'contactUser',
				'isLocalSystemBook' => true,
				'TEL' => [
					['value' => '+12025551234'],
				],
			]]);

		$context = new SignerSearchContext();
		$context->set('sms', '+12025551234', '+12025551234');

		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')
			->with('+12025551234', 'sms')
			->willReturn('+12025551234');

		$plugin = new ContactPhonePlugin(
			$appConfig,
			$contactsManager,
			$groupManager,
			$userManager,
			$userSession,
			$knownUserService,
			$context,
			$searchNormalizer,
		);

		$searchResult = new SearchResult();
		$hasMore = $plugin->search('+12025551234', 10, 0, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['contact-phone'] ?? [], $results['exact']['contact-phone'] ?? []);
		$this->assertCount(1, $items);
		$this->assertFalse($hasMore);
		$this->assertSame($items[0]['label'], '+12025551234');
	}

	public function testFilterGroups(): void {
		$appConfig1 = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'yes',
			'shareapi_restrict_user_enumeration_to_group' => 'yes',
			'shareapi_restrict_user_enumeration_to_phone' => 'yes',
			'shareapi_only_share_with_group_members' => 'no',
			'shareapi_only_share_with_group_members_exclude_group_list' => [],
		]);

		$currentUser = $this->createMock(IUser::class);
		$currentUser->method('getUID')->willReturn('current');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($currentUser);

		$contactUser1 = $this->createMock(IUser::class);
		$contactUser1->method('getUID')->willReturn('contactUser1');

		$contactUser2 = $this->createMock(IUser::class);
		$contactUser2->method('getUID')->willReturn('contactUser2');

		$contactUser3 = $this->createMock(IUser::class);
		$contactUser3->method('getUID')->willReturn('contactUser3');

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')
			->willReturnCallback(function (string $uid) use ($currentUser, $contactUser1, $contactUser2, $contactUser3) {
				if ($uid === 'current') {
					return $currentUser;
				}
				if ($uid === 'contactUser1') {
					return $contactUser1;
				}
				if ($uid === 'contactUser2') {
					return $contactUser2;
				}
				if ($uid === 'contactUser3') {
					return $contactUser3;
				}
				return null;
			});

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')
			->willReturnCallback(function ($user) use ($currentUser, $contactUser1, $contactUser2, $contactUser3): array {
				$uid = $user instanceof IUser ? $user->getUID() : (string)$user;
				if ($uid === 'current') {
					return ['sales'];
				}
				if ($uid === 'contactUser1') {
					return ['marketing'];
				}
				if ($uid === 'contactUser2') {
					return ['sales'];
				}
				if ($uid === 'contactUser3') {
					return ['sales'];
				}
				return [];
			});

		$knownUserService = $this->createMock(KnownUserService::class);
		$knownUserService->method('isKnownToUser')
			->willReturnCallback(function (string $currentUid, string $targetUid): bool {
				return $targetUid !== 'contactUser2';
			});

		$contactsManager = $this->createMock(IManager::class);
		$contactsManager->method('isEnabled')->willReturn(true);
		$contactsManager->method('search')
			->willReturn([
				[
					'FN' => 'Contact One',
					'UID' => 'contactUser1',
					'isLocalSystemBook' => true,
					'TEL' => [['value' => '+12025551234']],
				],
				[
					'FN' => 'Contact Two',
					'UID' => 'contactUser2',
					'isLocalSystemBook' => true,
					'TEL' => [['value' => '+12025551234']],
				],
				[
					'FN' => 'Contact Three',
					'UID' => 'contactUser3',
					'isLocalSystemBook' => true,
					'TEL' => [['value' => '+12025551234']],
				],
			]);

		$context = new SignerSearchContext();
		$context->set('sms', '+12025551234', '+12025551234');

		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')
			->willReturnCallback(fn (string $number) => $number);

		$plugin1 = new ContactPhonePlugin(
			$appConfig1,
			$contactsManager,
			$groupManager,
			$userManager,
			$userSession,
			$knownUserService,
			$context,
			$searchNormalizer,
		);

		$searchResult1 = new SearchResult();
		$plugin1->search('+12025551234', 10, 0, $searchResult1);

		$results1 = $searchResult1->asArray();
		$items1 = array_merge($results1['contact-phone'] ?? [], $results1['exact']['contact-phone'] ?? []);

		$this->assertCount(1, $items1);
		$this->assertSame('Contact Three', $items1[0]['label']);

		$appConfig2 = $this->applyAppConfig([
			'shareapi_allow_share_dialog_user_enumeration' => 'yes',
			'shareapi_only_share_with_group_members' => 'yes',
			'shareapi_only_share_with_group_members_exclude_group_list' => [],
		]);

		$plugin2 = new ContactPhonePlugin(
			$appConfig2,
			$contactsManager,
			$groupManager,
			$userManager,
			$userSession,
			$knownUserService,
			$context,
			$searchNormalizer,
		);

		$searchResult2 = new SearchResult();
		$plugin2->search('+12025551234', 10, 0, $searchResult2);

		$results2 = $searchResult2->asArray();
		$items2 = array_merge($results2['contact-phone'] ?? [], $results2['exact']['contact-phone'] ?? []);

		$this->assertCount(1, $items2);
	}

	public static function providerSearchScenarios(): array {
		return [
			'non phone method' => [
				'method' => 'email',
				'config' => [],
				'knownUser' => false,
				'currentGroups' => ['sales'],
				'contactGroups' => ['sales'],
				'isSystemBook' => true,
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
				'contactGroups' => ['sales'],
				'isSystemBook' => true,
				'expectedCount' => 0,
				'expectedHasMore' => false,
			],
			'enumeration allowed without restrictions' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
					'shareapi_restrict_user_enumeration_to_group' => 'no',
					'shareapi_restrict_user_enumeration_to_phone' => 'no',
					'shareapi_only_share_with_group_members' => 'no'
				],
				'knownUser' => false,
				'currentGroups' => ['sales'],
				'contactGroups' => ['engineering'],
				'isSystemBook' => true,
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
				'contactGroups' => ['engineering'],
				'isSystemBook' => true,
				'expectedCount' => 0,
			],
			'restrict to group with common group' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
					'shareapi_restrict_user_enumeration_to_group' => 'yes',
					'shareapi_restrict_user_enumeration_to_phone' => 'no',
				],
				'knownUser' => false,
				'currentGroups' => ['sales'],
				'contactGroups' => ['sales'],
				'isSystemBook' => true,
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
				'contactGroups' => ['sales'],
				'isSystemBook' => true,
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
				'contactGroups' => ['engineering'],
				'isSystemBook' => true,
				'expectedCount' => 0,
			],
			'non system address book ignores group restrictions' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
					'shareapi_only_share_with_group_members' => 'yes',
					'shareapi_restrict_user_enumeration_to_group' => 'yes',
				],
				'knownUser' => false,
				'currentGroups' => ['sales'],
				'contactGroups' => ['engineering'],
				'isSystemBook' => false,
				'expectedCount' => 1,
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
				'contactGroups' => ['sales'],
				'isSystemBook' => true,
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
