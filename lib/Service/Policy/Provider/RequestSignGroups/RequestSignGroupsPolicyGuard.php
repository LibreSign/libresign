<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\RequestSignGroups;

use OCP\Group\ISubAdmin;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;

final class RequestSignGroupsPolicyGuard {
	private const USER_SCOPE_NOT_SUPPORTED_MESSAGE = 'User-level scope is not supported for this policy';

	public function __construct(
		private IL10N $l10n,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private ISubAdmin $subAdmin,
	) {
	}

	public function assertUserScopeSupported(string $policyKey): void {
		if ($policyKey !== RequestSignGroupsPolicy::KEY) {
			return;
		}

		throw new \InvalidArgumentException($this->l10n->t(self::USER_SCOPE_NOT_SUPPORTED_MESSAGE));
	}

	public function normalizeManagedValue(string $policyKey, mixed $value, bool $allowNullReset = false): mixed {
		if ($policyKey !== RequestSignGroupsPolicy::KEY) {
			return $value;
		}

		if ($allowNullReset && $value === null) {
			return null;
		}

		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			throw new \InvalidArgumentException($this->l10n->t('Not allowed to manage this policy'));
		}

		$groupIds = RequestSignGroupsPolicyValue::decode($value);
		if ($groupIds === []) {
			throw new \InvalidArgumentException($this->l10n->t('At least one authorized group is required'));
		}

		$allowedGroupIds = $this->resolveAllowedGroupIdsForActor($user);
		$unknownGroupIds = array_values(array_diff($groupIds, $allowedGroupIds));
		if ($unknownGroupIds !== []) {
			throw new \InvalidArgumentException($this->l10n->t('One or more selected groups are not allowed for your administration scope'));
		}

		return RequestSignGroupsPolicyValue::encode($groupIds);
	}

	/** @return list<string> */
	private function resolveAllowedGroupIdsForActor(IUser $user): array {
		if ($this->groupManager->isAdmin($user->getUID())) {
			return array_values(array_map(
				static fn (IGroup $group): string => $group->getGID(),
				$this->groupManager->search(''),
			));
		}

		if (!$this->subAdmin->isSubAdmin($user)) {
			return [];
		}

		return array_values(array_map(
			static fn (IGroup $group): string => $group->getGID(),
			$this->subAdmin->getSubAdminsGroups($user),
		));
	}
}
