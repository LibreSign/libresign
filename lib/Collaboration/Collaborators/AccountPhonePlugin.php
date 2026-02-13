<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Collaboration\Collaborators;

use OC\KnownUser\KnownUserService;
use OCA\Libresign\Service\Identify\SearchNormalizer;
use OCA\Libresign\Service\Identify\SignerSearchContext;
use OCP\Accounts\IAccountManager;
use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;

class AccountPhonePlugin implements ISearchPlugin {
	public const TYPE_SIGNER_ACCOUNT_PHONE = 51;
	private const PHONE_BASED_METHODS = ['whatsapp', 'sms', 'telegram', 'signal'];

	public function __construct(
		private IAppConfig $appConfig,
		private IAccountManager $accountManager,
		private IGroupManager $groupManager,
		private IUserSession $userSession,
		private KnownUserService $knownUserService,
		private IUserManager $userManager,
		private SignerSearchContext $searchContext,
		private SearchNormalizer $searchNormalizer,
	) {
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function search($search, $limit, $offset, ISearchResult $searchResult): bool {
		$method = $this->searchContext->getMethod();
		$search = trim((string)$search);

		if ($search === '' || !in_array($method, self::PHONE_BASED_METHODS, true)) {
			return false;
		}

		$currentUser = $this->userSession->getUser();
		if ($currentUser === null) {
			return false;
		}

		$shareeEnumeration = $this->appConfig->getValueString('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes') === 'yes';
		$shareeEnumerationFullMatch = $this->appConfig->getValueString('core', 'shareapi_restrict_user_enumeration_full_match', 'yes') === 'yes';
		if (!$shareeEnumeration && !$shareeEnumerationFullMatch) {
			return false;
		}

		$shareeEnumerationRestrictToGroup = $this->appConfig->getValueString('core', 'shareapi_restrict_user_enumeration_to_group', 'no') === 'yes';
		$shareeEnumerationRestrictToPhone = $this->appConfig->getValueString('core', 'shareapi_restrict_user_enumeration_to_phone', 'no') === 'yes';
		$shareWithGroupOnly = $this->appConfig->getValueString('core', 'shareapi_only_share_with_group_members', 'no') === 'yes';
		$shareWithGroupOnlyExcludeGroupsList = json_decode(
			$this->appConfig->getValueString('core', 'shareapi_only_share_with_group_members_exclude_group_list', '[]'),
			true,
			512,
			JSON_THROW_ON_ERROR
		) ?? [];
		$allowedGroups = array_diff($this->groupManager->getUserGroupIds($currentUser), $shareWithGroupOnlyExcludeGroupsList);

		$matches = $this->accountManager->searchUsers(IAccountManager::PROPERTY_PHONE, [$search]);
		$items = [];
		foreach ($matches as $phone => $userId) {
			// Filter out users with phone numbers that cannot be normalized
			$normalizedPhone = $this->searchNormalizer->tryNormalizePhoneNumber((string)$phone, $method);
			if ($normalizedPhone === null) {
				continue;
			}

			$userId = (string)$userId;
			if ($userId === $currentUser->getUID()) {
				continue;
			}
			$user = $this->userManager->get($userId);
			if ($user === null || !$user->isEnabled()) {
				continue;
			}
			$userGroups = $this->groupManager->getUserGroupIds($user);
			$inAllowedGroup = array_intersect($allowedGroups, $userGroups) !== [];

			if ($shareeEnumeration) {
				$allowedByRestriction = true;
				if ($shareeEnumerationRestrictToGroup || $shareeEnumerationRestrictToPhone) {
					$allowedByRestriction = false;
					if ($shareeEnumerationRestrictToGroup && $inAllowedGroup) {
						$allowedByRestriction = true;
					}
					if ($shareeEnumerationRestrictToPhone
						&& $this->knownUserService->isKnownToUser($currentUser->getUID(), $userId)) {
						$allowedByRestriction = true;
					}
				}
				if (!$allowedByRestriction) {
					continue;
				}
			} elseif (!$shareeEnumerationFullMatch) {
				continue;
			}

			if ($shareWithGroupOnly && !$inAllowedGroup) {
				continue;
			}

			$displayName = $user->getDisplayName() !== '' ? $user->getDisplayName() : $userId;
			$items[] = [
				'label' => $displayName,
				'shareWithDisplayNameUnique' => $normalizedPhone,
				'method' => $method,
				'value' => [
					'shareType' => self::TYPE_SIGNER_ACCOUNT_PHONE,
					'shareWith' => $normalizedPhone,
				],
			];
		}

		$hasMore = count($items) > ($offset + $limit);
		$pagedItems = array_slice($items, $offset, $limit);

		$result = ['wide' => [], 'exact' => []];
		$searchLower = strtolower($search);
		foreach ($pagedItems as $item) {
			if (strtolower($item['shareWithDisplayNameUnique']) === $searchLower
				|| strtolower($item['label']) === $searchLower
			) {
				$result['exact'][] = $item;
			} else {
				$result['wide'][] = $item;
			}
		}

		$type = new SearchResultType('account-phone');
		$searchResult->addResultSet($type, $result['wide'], $result['exact']);

		return $hasMore;
	}
}
