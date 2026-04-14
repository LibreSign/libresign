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
