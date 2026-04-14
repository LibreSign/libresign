<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCP\Group\ISubAdmin;
use OCP\IGroupManager;
use OCP\IUser;

final class PolicyAuthorizationService implements IPolicyAuthorizationService {
	public function __construct(
		private IGroupManager $groupManager,
		private ISubAdmin $subAdmin,
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
	 * Get list of group IDs manageable by the given user through subadmin scope.
	 *
	 * For instance admins: returns empty (they manage all groups at policy level).
	 * For subadmins: returns groups they are subadmin of.
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

		// Only subadmins have a restricted manageable group scope
		return array_values(array_map(
			static fn ($group): string => $group->getGID(),
			$this->subAdmin->getSubAdminsGroups($user),
		));
	}
}
