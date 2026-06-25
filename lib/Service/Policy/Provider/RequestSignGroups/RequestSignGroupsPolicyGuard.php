<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\RequestSignGroups;

use OCA\Libresign\Service\Policy\PolicyAuthorizationService;
use OCP\Group\ISubAdmin;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;

final class RequestSignGroupsPolicyGuard {
	public function __construct(
		private IL10N $l10n,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private ISubAdmin $subAdmin,
		private PolicyAuthorizationService $policyAuthorizationService,
	) {
	}

	public function assertUserScopeSupported(string $policyKey): void {
		if ($policyKey !== RequestSignGroupsPolicy::KEY) {
			return;
		}

		throw new \InvalidArgumentException($this->l10n->t('User-level scope is not supported for this policy'));
	}

	public function normalizeManagedValue(string $policyKey, mixed $value, bool $allowNullReset = false, ?string $requiredGroupId = null): mixed {
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

		$decodedPolicy = RequestSignGroupsPolicyValue::decodePolicy($value);
		$allowGroupIds = $decodedPolicy['allowGroups'];
		$denyGroupIds = $decodedPolicy['denyGroups'];

		if ($allowGroupIds === []) {
			throw new \InvalidArgumentException($this->l10n->t('At least one authorized group is required'));
		}

		$isSystemAdmin = $this->groupManager->isAdmin($user->getUID());
		if (!$isSystemAdmin
			&& is_string($requiredGroupId)
			&& trim($requiredGroupId) !== ''
			&& !in_array($requiredGroupId, $allowGroupIds, true)) {
			throw new \InvalidArgumentException($this->l10n->t('You cannot remove your managed group from this rule'));
		}

		$allowedGroupIds = $this->resolveAllowedGroupIdsForActor($user);
		$groupsToValidate = array_values(array_unique(array_merge($allowGroupIds, $denyGroupIds)));
		$unknownGroupIds = array_values(array_diff($groupsToValidate, $allowedGroupIds));
		if ($unknownGroupIds !== []) {
			throw new \InvalidArgumentException($this->l10n->t('One or more selected groups are not allowed for your administration scope'));
		}

		return RequestSignGroupsPolicyValue::encode($decodedPolicy);
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

		return $this->policyAuthorizationService->getManageablePolicyGroupIds($user);
	}
}
