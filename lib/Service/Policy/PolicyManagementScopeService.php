<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCP\Group\ISubAdmin;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;

final class PolicyManagementScopeService {
	public function __construct(
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private ISubAdmin $subAdmin,
		private PolicyService $policyService,
		private PolicyAuthorizationService $policyAuthorizationService,
	) {
	}

	/**
	 * @return array<string, array{groupCount: int, userCount: int, everyoneCount: int}>
	 */
	public function resolveVisibleRuleCountsForCurrentActor(): array {
		return $this->resolveVisibleRuleCountsForActor($this->userSession->getUser());
	}

	public function canCurrentActorManageGroupPolicy(string $groupId, string $policyKey): bool {
		return $this->canManageGroupPolicy($this->userSession->getUser(), $groupId, $policyKey);
	}

	public function canCurrentActorManageUserPolicy(string $userId): bool {
		return $this->canManageUserPolicy($this->userSession->getUser(), $userId);
	}

	public function canCurrentActorManageScopedUserPolicy(string $userId, string $policyKey): bool {
		$user = $this->userSession->getUser();

		return $this->canManageUserPolicy($user, $userId)
			&& $this->policyService->canManageUserPolicyForUserId($policyKey, $userId);
	}

	/**
	 * @return list<string>|null Null means the current actor can manage all groups.
	 */
	public function resolveCurrentActorManageableGroupIdsForPolicy(string $policyKey): ?array {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			return [];
		}

		if ($this->groupManager->isAdmin($user->getUID())) {
			return null;
		}

		if (!$this->subAdmin->isSubAdmin($user)) {
			return [];
		}

		return $this->resolveManageableGroupIdsForPolicy($user, $policyKey);
	}

	private function canManageGroupPolicy(?IUser $user, string $groupId, string $policyKey): bool {
		if (!$user instanceof IUser) {
			return false;
		}

		if ($this->groupManager->isAdmin($user->getUID())) {
			return true;
		}

		if (!$this->subAdmin->isSubAdmin($user)) {
			return false;
		}

		return in_array($groupId, $this->resolveManageableGroupIdsForPolicy($user, $policyKey), true);
	}

	private function canManageUserPolicy(?IUser $user, string $userId): bool {
		if (!$user instanceof IUser) {
			return false;
		}

		if ($this->groupManager->isAdmin($user->getUID())) {
			return true;
		}

		if (!$this->subAdmin->isSubAdmin($user)) {
			return false;
		}

		$targetUser = $this->userManager->get($userId);
		if (!$targetUser instanceof IUser) {
			return false;
		}

		$managedGroupIds = array_values(array_map(
			static fn ($group): string => $group->getGID(),
			$this->subAdmin->getSubAdminsGroups($user),
		));
		if ($managedGroupIds === []) {
			return false;
		}

		$targetGroupIds = $this->groupManager->getUserGroupIds($targetUser);
		return array_intersect($managedGroupIds, $targetGroupIds) !== [];
	}

	/**
	 * @return array<string, array{groupCount: int, userCount: int, everyoneCount: int}>
	 */
	private function resolveVisibleRuleCountsForActor(?IUser $user): array {
		if ($user === null) {
			return [];
		}

		if ($this->groupManager->isAdmin($user->getUID())) {
			return $this->policyService->getAllRuleCounts();
		}

		if ($this->subAdmin->isSubAdmin($user)) {
			$groupIds = $this->resolveSubAdminManagedGroupIds($user);
			return $this->filterVisibleRuleCountsForManagedGroups(
				$this->policyService->getRuleCounts($groupIds, []),
				$user,
				$groupIds,
			);
		}

		return [];
	}

	/**
	 * @param array<string, array{groupCount?: int, userCount?: int, everyoneCount?: int}> $ruleCounts
	 * @param list<string> $groupIds
	 * @return array<string, array{groupCount: int, userCount: int, everyoneCount: int}>
	 */
	private function filterVisibleRuleCountsForManagedGroups(array $ruleCounts, IUser $user, array $groupIds): array {
		/** @var array<string, array{groupCount: int, userCount: int, everyoneCount: int}> $normalizedRuleCounts */
		$normalizedRuleCounts = [];
		foreach ($ruleCounts as $policyKey => $counts) {
			$normalizedRuleCounts[$policyKey] = $this->normalizeRuleCounts($counts);
		}

		$groupIds = array_values(array_unique(array_filter(
			$groupIds,
			static fn (string $groupId): bool => trim($groupId) !== '',
		)));

		if ($groupIds === []) {
			return $normalizedRuleCounts;
		}

		foreach ($normalizedRuleCounts as $policyKey => $counts) {
			if (($counts['groupCount'] ?? 0) <= 0) {
				continue;
			}

			if (!$this->policyService->shouldFilterVisibleGroupCountsForCurrentActor($policyKey)) {
				continue;
			}

			$counts['groupCount'] = $this->policyService->countVisibleGroupPoliciesForTargets(
				$policyKey,
				$this->resolveManageableGroupIdsForPolicy($user, $policyKey),
			);
			$normalizedRuleCounts[$policyKey] = $counts;
		}

		return $normalizedRuleCounts;
	}

	/**
	 * @param array{groupCount?: int, userCount?: int, everyoneCount?: int} $counts
	 * @return array{groupCount: int, userCount: int, everyoneCount: int}
	 */
	private function normalizeRuleCounts(array $counts): array {
		return [
			'groupCount' => (int)($counts['groupCount'] ?? 0),
			'userCount' => (int)($counts['userCount'] ?? 0),
			'everyoneCount' => (int)($counts['everyoneCount'] ?? 0),
		];
	}

	/** @return list<string> */
	private function resolveSubAdminManagedGroupIds(IUser $user): array {
		return array_values(array_filter(
			$this->groupManager->getUserGroupIds($user),
			static fn (mixed $groupId): bool => is_string($groupId) && trim($groupId) !== '',
		));
	}

	/** @return list<string> */
	private function resolveManageableGroupIdsForPolicy(IUser $user, string $policyKey): array {
		if ($policyKey === RequestSignGroupsPolicy::KEY) {
			return $this->policyAuthorizationService->getManageablePolicyGroupIds($user);
		}

		return $this->resolveSubAdminManagedGroupIds($user);
	}
}
