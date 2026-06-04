<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCP\Group\ISubAdmin;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;

final class PolicyAuthorizationService implements IPolicyAuthorizationService {
	public function __construct(
		private IGroupManager $groupManager,
		private ISubAdmin $subAdmin,
		private PolicyService $policyService,
	) {
	}

	/**
	 * Check if the user can manage group policies.
	 *
	 * Instance admins and subadmins can manage group policies.
	 * Regular users cannot.
	 */
	#[\Override]
	public function canUserManageGroupPolicies(?IUser $user): bool {
		if ($user === null) {
			return false;
		}

		return $this->groupManager->isAdmin($user->getUID())
			|| $this->subAdmin->isSubAdmin($user);
	}

	/**
	 * Get list of group IDs manageable by the given user.
	 *
	 * For instance admins: returns empty (all groups are manageable).
	 * For subadmins: returns all groups they subadmin-manage, but only after the
	 * request-sign policy itself has been explicitly delegated by the system
	 * administrator (exposed as editable for the current actor).
	 * For regular users: returns empty.
	 *
	 * @return list<string>
	 */
	#[\Override]
	public function getManageablePolicyGroupIds(?IUser $user): array {
		if ($user === null) {
			return [];
		}

		// Instance admins do not need a restricted group list
		// (they have access to all groups at the policy layer)
		if ($this->groupManager->isAdmin($user->getUID())) {
			return [];
		}

		if (!$this->subAdmin->isSubAdmin($user)) {
			return [];
		}

		$managedGroupIds = array_values(array_unique(array_filter(array_map(
			static fn (IGroup $group): string => trim($group->getGID()),
			$this->subAdmin->getSubAdminsGroups($user),
		), static fn (string $groupId): bool => $groupId !== '')));

		if ($managedGroupIds === []) {
			return [];
		}

		$requestSignPolicy = $this->policyService->resolveForUser(RequestSignGroupsPolicy::KEY, $user);
		if (!$requestSignPolicy->isEditableByCurrentActor()) {
			foreach ($this->policyService->listGroupPoliciesForTargets(RequestSignGroupsPolicy::KEY, $managedGroupIds) as $record) {
				$policy = $record['policy'] ?? null;
				if (!$policy instanceof PolicyLayer) {
					continue;
				}

				if (!$this->wasGroupPolicyCreatedBySystemAdmin($policy)) {
					return $managedGroupIds;
				}

				if ($policy->isAllowChildOverride()) {
					return $managedGroupIds;
				}
			}

			return [];
		}

		return $managedGroupIds;
	}

	private function wasGroupPolicyCreatedBySystemAdmin(PolicyLayer $policy): bool {
		return $policy->isCreatedBySystemAdmin();
	}
}
