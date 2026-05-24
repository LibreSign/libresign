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
	 * Get list of group IDs manageable by the given user.
	 *
	 * For instance admins: returns empty (they manage all groups at policy level).
	 * For other users: returns groups they belong to (admin, subadmin, or member).
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

		// For group admins and regular members: return all groups they belong to
		// This allows them to authorize any group they're part of for policy rules
		$groupIds = $this->groupManager->getUserGroupIds($user);

		$groupIds = array_filter(
			$groupIds,
			static fn (string $groupId): bool => trim($groupId) !== '',
		);

		return array_values(array_unique($groupIds));
	}
}
