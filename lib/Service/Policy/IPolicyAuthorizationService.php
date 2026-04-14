<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCP\IUser;

interface IPolicyAuthorizationService {
	/**
	 * Check if the user can manage group policies.
	 *
	 * Instance admins and subadmins can manage group policies.
	 * Regular users cannot.
	 */
	public function canUserManageGroupPolicies(?IUser $user): bool;

	/**
	 * Get list of group IDs manageable by the given user through subadmin scope.
	 *
	 * For instance admins: returns empty (they manage all groups at policy level).
	 * For subadmins: returns groups they are subadmin of.
	 * For regular users: returns empty.
	 *
	 * @return list<string>
	 */
	public function getManageablePolicyGroupIds(?IUser $user): array;
}
