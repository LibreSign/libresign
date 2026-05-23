<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicyValue;
use OCP\IGroupManager;
use OCP\IUser;

class RequestSignAuthorizationService {
	public function __construct(
		private PolicyService $policyService,
		private IGroupManager $groupManager,
	) {
	}

	/**
	 * Check if a user is authorized to create signature requests.
	 *
	 * This evaluates the delegated RBAC policy 'groups_request_sign' which controls
	 * which groups are authorized to create signature requests within the current scope.
	 * Administrators can only authorize groups they themselves belong to.
	 *
	 * @param IUser|null $user The user to check (typically current user)
	 * @return bool True if user belongs to at least one authorized requester group
	 */
	public function canRequestSign(?IUser $user = null): bool {
		if (!$user instanceof IUser) {
			return false;
		}

		$resolvedPolicy = $this->policyService->resolveForUser(RequestSignGroupsPolicy::KEY, $user);
		$authorizedGroups = RequestSignGroupsPolicyValue::decode($resolvedPolicy->getEffectiveValue());
		if ($authorizedGroups === []) {
			return false;
		}

		$userGroups = $this->groupManager->getUserGroupIds($user);
		return array_intersect($userGroups, $authorizedGroups) !== [];
	}
}
