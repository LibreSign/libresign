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
			->willReturnCallback(function (string $uid) use ($contactUser): ?IUser {
				return $uid === 'contactUser' ? $contactUser : null;
			});

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

		$plugin = new ContactPhonePlugin(
			$appConfig,
			$contactsManager,
			$groupManager,
			$userManager,
			$userSession,
			$knownUserService,
			$context,
			$this->createSearchNormalizerMock('+12025551234'),
		);

		$searchResult = new SearchResult();
		$plugin->search('+12025551234', 10, 0, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['contact-phone'] ?? [], $results['exact']['contact-phone'] ?? []);
		$this->assertCount($expectedCount, $items);
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
		$userManager->method('get')->willReturn(null);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['sales']);

		$knownUserService = $this->createMock(KnownUserService::class);
		$knownUserService->method('isKnownToUser')->willReturn(true);

		$contactsManager = $this->createMock(IManager::class);
		$contactsManager->method('isEnabled')->willReturn(true);
		$contactsManager->method('search')
			->willReturn([[
				'FN' => 'Contact Name',
				'isLocalSystemBook' => true,
				'TEL' => [
					['value' => '+12025551234'],
				],
			]]);

		$context = new SignerSearchContext();
		$context->set('sms', '+12025551234', '+12025551234');

		$plugin = new ContactPhonePlugin(
			$appConfig,
			$contactsManager,
			$groupManager,
			$userManager,
			$userSession,
			$knownUserService,
			$context,			$this->createSearchNormalizerMock('+12025551234'),		);

		$searchResult = new SearchResult();
		$plugin->search('+12025551234', 10, 0, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['contact-phone'] ?? [], $results['exact']['contact-phone'] ?? []);
		$this->assertCount(0, $items);
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
					['value' => '+12025550001'],
					['value' => '+12025550002'],
					['value' => '+12025550003'],
				],
			]]);

		$context = new SignerSearchContext();
		$context->set('sms', '+12025550001', '+12025550001');

		$plugin = new ContactPhonePlugin(
			$appConfig,
			$contactsManager,
			$groupManager,
			$userManager,
			$userSession,
			$knownUserService,
			$context,
			$this->createSearchNormalizerMock('+12025550001'),
		);

		$searchResult = new SearchResult();
		$hasMore = $plugin->search('+12025550001', 1, 1, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['contact-phone'] ?? [], $results['exact']['contact-phone'] ?? []);
		$this->assertCount(1, $items);
		$this->assertTrue($hasMore);
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
			->willReturnCallback(function (string $uid) use ($contactUser): ?IUser {
				return $uid === 'contactUser' ? $contactUser : null;
			});

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

		$plugin = new ContactPhonePlugin(
			$appConfig,
			$contactsManager,
			$groupManager,
			$userManager,
			$userSession,
			$knownUserService,
			$context,
			$this->createSearchNormalizerMock('+12025551234'),
		);

		$searchResult = new SearchResult();
		$plugin->search('+12025551234', 10, 0, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['contact-phone'] ?? [], $results['exact']['contact-phone'] ?? []);
		$this->assertSame(ContactPhonePlugin::TYPE_SIGNER_CONTACT_PHONE, $items[0]['value']['shareType']);
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
		// Contact with phone number that cannot be normalized (missing area code)
		$contactsManager->method('search')
			->willReturn([[
				'FN' => 'Contact Name',
				'UID' => 'contactUser',
				'isLocalSystemBook' => true,
				'TEL' => [['value' => '999999999']], // Missing DDD
			]]);

		$context = new SignerSearchContext();
		$context->set('sms', '999999999', '999999999');

		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')
			->with('999999999', 'sms')
			->willReturn(null); // Cannot normalize

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
		$plugin->search('999999999', 10, 0, $searchResult);

		$results = $searchResult->asArray();
		$items = array_merge($results['contact-phone'] ?? [], $results['exact']['contact-phone'] ?? []);
		$this->assertCount(0, $items); // Contact should be filtered out
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
			],
			'enumeration allowed without restrictions' => [
				'method' => 'sms',
				'config' => [
					'shareapi_allow_share_dialog_user_enumeration' => 'yes',
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

	private function createSearchNormalizerMock(string $phoneNumber): SearchNormalizer {
		$searchNormalizer = $this->createMock(SearchNormalizer::class);
		$searchNormalizer->method('tryNormalizePhoneNumber')
			->willReturn($phoneNumber); // Return the normalized number
		return $searchNormalizer;
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
