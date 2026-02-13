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
use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\Contacts\IManager;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;

class ContactPhonePlugin implements ISearchPlugin {
	public const TYPE_SIGNER_CONTACT_PHONE = 52;
	private const PHONE_BASED_METHODS = ['whatsapp', 'sms', 'telegram', 'signal'];

	public function __construct(
		private IAppConfig $appConfig,
		private IManager $contactsManager,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private IUserSession $userSession,
		private KnownUserService $knownUserService,
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
		$currentUser = $this->userSession->getUser();
		if ($currentUser === null) {
			return false;
		}
		$allowedGroups = array_diff($this->groupManager->getUserGroupIds($currentUser), $shareWithGroupOnlyExcludeGroupsList);

		if (!$this->contactsManager->isEnabled()) {
			return false;
		}

		$max = $offset + $limit + 1;
		$rows = [];
		$seen = [];

		$contacts = $this->contactsManager->search(
			$search,
			['TEL', 'FN'],
			[
				'limit' => $max,
				'offset' => 0,
				'types' => true,
				'enumeration' => $shareeEnumeration,
				'fullmatch' => $shareeEnumerationFullMatch,
			]
		);

		foreach ($contacts as $contact) {
			if (!empty($contact['isLocalSystemBook'])) {
				$contactUserId = $contact['UID'] ?? null;
				if (!is_string($contactUserId) || $contactUserId === '') {
					continue;
				}

				$contactUser = $this->userManager->get($contactUserId);
				if ($contactUser === null) {
					continue;
				}
				$contactGroups = $this->groupManager->getUserGroupIds($contactUser);
				$inAllowedGroup = !empty(array_intersect($contactGroups, $allowedGroups));

				if ($shareeEnumerationRestrictToGroup && !$inAllowedGroup) {
					continue;
				}
				if ($shareeEnumerationRestrictToPhone
					&& !$this->knownUserService->isKnownToUser($currentUser->getUID(), $contactUserId)) {
					continue;
				}
				if ($shareWithGroupOnly && !$inAllowedGroup) {
					continue;
				}
			}

			$displayName = $contact['FN'] ?? '';
			foreach ($this->extractPhoneValues($contact) as $phoneValue) {
				$normalizedPhone = $this->searchNormalizer->tryNormalizePhoneNumber($phoneValue, $method);
				if ($normalizedPhone === null) {
					continue;
				}

				if (isset($seen[$normalizedPhone])) {
					continue;
				}
				$rows[] = [
					'label' => $displayName !== '' ? $displayName : $normalizedPhone,
					'shareWithDisplayNameUnique' => $normalizedPhone,
					'method' => $method,
					'value' => [
						'shareType' => self::TYPE_SIGNER_CONTACT_PHONE,
						'shareWith' => $normalizedPhone,
					],
				];
				$seen[$normalizedPhone] = true;
				if (count($rows) >= $max) {
					break 2;
				}
			}
		}

		$hasMore = count($rows) > ($offset + $limit);
		$pagedRows = array_slice($rows, $offset, $limit);

		$result = ['wide' => [], 'exact' => []];
		$searchLower = strtolower($search);
		foreach ($pagedRows as $item) {
			if (strtolower($item['shareWithDisplayNameUnique']) === $searchLower
				|| strtolower($item['label']) === $searchLower
			) {
				$result['exact'][] = $item;
			} else {
				$result['wide'][] = $item;
			}
		}

		$type = new SearchResultType('contact-phone');
		$searchResult->addResultSet($type, $result['wide'], $result['exact']);

		return $hasMore;
	}

	private function extractPhoneValues(array $contact): array {
		if (!isset($contact['TEL'])) {
			return [];
		}
		$phones = $contact['TEL'];
		if (is_string($phones)) {
			return [$phones];
		}
		if (is_array($phones) && isset($phones['value'])) {
			$phones = [$phones];
		}
		if (!is_array($phones)) {
			return [];
		}
		$values = [];
		foreach ($phones as $phone) {
			if (is_array($phone)) {
				$values[] = (string)($phone['value'] ?? '');
			} elseif (is_string($phone)) {
				$values[] = $phone;
			}
		}
		return array_filter($values, fn (string $value) => $value !== '');
	}
}
