<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Service\Policy\PolicyService;
use OCP\Group\ISubAdmin;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;

/**
 * Scopes CSC integration administration and signing to existing LibreSign
 * system/group/account policy rules. No implicit account or group grant.
 *
 * Secrets never become policy values. Only records owned by the selected
 * policy scope can be managed by that scope's delegated administrators.
 */
final class ExternalSigningIntegrationScopeAccess {
	private const POLICY_KEY = 'external_signing_enabled';

	public function __construct(
		private PolicyService $policies,
		private IGroupManager $groups,
		private IUserManager $users,
		private IUserSession $session,
		private ISubAdmin $subAdmin,
	) {
	}

	public function canManage(string $scopeType, string $scopeId): bool {
		ExternalIntegrationStore::validateScope($scopeType, $scopeId);
		$actor = $this->session->getUser();
		if (!$actor instanceof IUser) {
			return false;
		}
		if ($this->groups->isAdmin($actor->getUID())) {
			return true;
		}
		try {
			if ($scopeType === 'group') {
				if (!$this->isSubAdminOfGroup($actor, $scopeId)) {
					return false;
				}
				$system = $this->policies->getSystemPolicy(self::POLICY_KEY);
				// A locked system rule must never leave a previously granted
				// group-manager override active after delegation is revoked.
				if ($system !== null && (!$system->isVisibleToChild() || !$system->isAllowChildOverride())) {
					return false;
				}
				$rule = $this->policies->getGroupPolicy(self::POLICY_KEY, $scopeId);
				return $rule !== null
					&& $rule->getValue() === true
					&& $rule->isVisibleToChild()
					&& $rule->isAllowChildOverride();
			}
			if ($scopeType === 'user' && hash_equals($actor->getUID(), $scopeId)) {
				$rule = $this->policies->getUserPolicyForUserId(self::POLICY_KEY, $scopeId);
				return $rule !== null
					&& $rule->getValue() === true
					&& $rule->isVisibleToChild()
					&& $rule->isAllowChildOverride()
					&& $this->policies->resolveForUserId(self::POLICY_KEY, $scopeId)
						->getEffectiveValueAsBool(false);
			}
		} catch (\InvalidArgumentException|\DomainException) {
			// The admin-backend PR can be installed before the policy frontend
			// is merged. Missing policies must not enable delegated access.
			return false;
		}
		return false;
	}

	public function canManageAny(): bool {
		$actor = $this->session->getUser();
		if (!$actor instanceof IUser) {
			return false;
		}
		if ($this->groups->isAdmin($actor->getUID()) || $this->canManage('user', $actor->getUID())) {
			return true;
		}
		if (!$this->subAdmin->isSubAdmin($actor)) {
			return false;
		}
		foreach ($this->subAdmin->getSubAdminsGroups($actor) as $group) {
			if ($this->canManage('group', $group->getGID())) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The document/request owner defines an organization's provider context.
	 * A guest may use an owner's authorized group integration without
	 * learning the CSC credentials. Personal integrations, in contrast,
	 * require the authenticated signer's own account identity.
	 */
	public function canUse(ExternalIntegrationRecord $record, string $requestOwnerUid, ?string $signerUid): bool {
		if (!$record->enabled || $requestOwnerUid === '') {
			return false;
		}
		try {
			if ($record->scopeType === 'system') {
				return $this->policies->resolveForUserId(self::POLICY_KEY, $requestOwnerUid)
					->getEffectiveValueAsBool(false);
			}
			if ($record->scopeType === 'group') {
				$owner = $this->users->get($requestOwnerUid);
				if (!$owner instanceof IUser || !in_array($record->scopeId, $this->groups->getUserGroupIds($owner), true)) {
					return false;
				}
				$rule = $this->policies->getGroupPolicy(self::POLICY_KEY, $record->scopeId);
				return $rule !== null
					&& $rule->getValue() === true
					&& $rule->isVisibleToChild()
					&& $this->policies->resolveForUserId(self::POLICY_KEY, $requestOwnerUid)
						->getEffectiveValueAsBool(false);
			}
			if ($record->scopeType === 'user' && $signerUid !== null
				&& hash_equals($record->scopeId, $signerUid)) {
				$rule = $this->policies->getUserPolicyForUserId(self::POLICY_KEY, $signerUid);
				return $rule !== null
					&& $rule->getValue() === true
					&& $rule->isVisibleToChild()
					&& $this->policies->resolveForUserId(self::POLICY_KEY, $signerUid)
						->getEffectiveValueAsBool(false);
			}
		} catch (\InvalidArgumentException|\DomainException) {
			return false;
		}
		return false;
	}

	private function isSubAdminOfGroup(IUser $actor, string $groupId): bool {
		if (!$this->subAdmin->isSubAdmin($actor)) {
			return false;
		}
		foreach ($this->subAdmin->getSubAdminsGroups($actor) as $group) {
			if ($group->getGID() === $groupId) {
				return true;
			}
		}
		return false;
	}
}
