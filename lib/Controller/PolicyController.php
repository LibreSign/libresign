<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\PolicyService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Group\ISubAdmin;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;

/**
 * @psalm-import-type LibresignErrorResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignEffectivePolicyState from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignEffectivePoliciesResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignGroupPolicyResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignGroupPolicyState from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignGroupPolicyWriteResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignSystemPolicyResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignSystemPolicyWriteResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignUserPolicyResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignUserPolicyState from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignUserPolicyWriteResponse from \OCA\Libresign\ResponseDefinitions
 */
final class PolicyController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private IL10N $l10n,
		private PolicyService $policyService,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private ISubAdmin $subAdmin,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Effective policies bootstrap
	 *
	 * @return DataResponse<Http::STATUS_OK, LibresignEffectivePoliciesResponse, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/policies/effective', requirements: ['apiVersion' => '(v1)'])]
	public function effective(): DataResponse {
		$user = $this->userSession->getUser();
		$ruleCounts = $this->resolveRuleCountsForActor($user);

		/** @var array<string, LibresignEffectivePolicyState> $policies */
		$policies = [];
		foreach ($this->policyService->resolveKnownPolicies() as $policyKey => $resolvedPolicy) {
			/** @var LibresignEffectivePolicyState $policyState */
			$policyState = $resolvedPolicy->toArray();
			$policyState['groupCount'] = $ruleCounts[$policyKey]['groupCount'] ?? 0;
			$policyState['userCount'] = $ruleCounts[$policyKey]['userCount'] ?? 0;
			$policies[$policyKey] = $policyState;
		}

		/** @var LibresignEffectivePoliciesResponse $data */
		$data = [
			'policies' => $policies,
		];

		return new DataResponse($data);
	}

	/**
	 * Read explicit system policy configuration
	 *
	 * @param string $policyKey Policy identifier to read from the system layer.
	 * @return DataResponse<Http::STATUS_OK, LibresignSystemPolicyResponse, array{}>
	 *
	 * 200: OK
	 */
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/policies/system/{policyKey}', requirements: ['apiVersion' => '(v1)', 'policyKey' => '[a-z0-9_]+'])]
	public function getSystem(string $policyKey): DataResponse {
		$policy = $this->policyService->getSystemPolicy($policyKey);

		/** @var LibresignSystemPolicyResponse $data */
		$data = [
			'policy' => [
				'policyKey' => $policyKey,
				'scope' => ($policy?->getScope() === 'global' ? 'global' : 'system'),
				'value' => $policy?->getValue(),
				'allowChildOverride' => $policy?->isAllowChildOverride() ?? true,
				'visibleToChild' => $policy?->isVisibleToChild() ?? true,
				'allowedValues' => $policy?->getAllowedValues() ?? [],
			],
		];

		return new DataResponse($data);
	}

	/**
	 * Read a group-level policy value
	 *
	 * @param string $groupId Group identifier that receives the policy binding.
	 * @param string $policyKey Policy identifier to read for the selected group.
	 * @return DataResponse<Http::STATUS_OK, LibresignGroupPolicyResponse, array{}>|DataResponse<Http::STATUS_FORBIDDEN, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 403: Forbidden
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/policies/group/{groupId}/{policyKey}', requirements: ['apiVersion' => '(v1)', 'groupId' => '[^/]+', 'policyKey' => '[a-z0-9_]+'])]
	public function getGroup(string $groupId, string $policyKey): DataResponse {
		if (!$this->canManageGroupPolicy($groupId)) {
			return $this->forbiddenGroupPolicyResponse();
		}

		$policy = $this->policyService->getGroupPolicy($policyKey, $groupId);

		/** @var LibresignGroupPolicyResponse $data */
		$data = [
			'policy' => $this->serializeGroupPolicy($groupId, $policyKey, $policy),
		];

		return new DataResponse($data);
	}

	/**
	 * Read a user-level policy preference for a target user (admin scope)
	 *
	 * @param string $userId Target user identifier that receives the policy preference.
	 * @param string $policyKey Policy identifier to read for the selected user.
	 * @return DataResponse<Http::STATUS_OK, LibresignUserPolicyResponse, array{}>|DataResponse<Http::STATUS_FORBIDDEN, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 403: Forbidden
	 */
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/policies/user/{userId}/{policyKey}', requirements: ['apiVersion' => '(v1)', 'userId' => '[^/]+', 'policyKey' => '[a-z0-9_]+'])]
	public function getUserPolicyForUser(string $userId, string $policyKey): DataResponse {
		if (!$this->canManageUserPolicy($userId)) {
			return $this->forbiddenUserPolicyResponse();
		}

		$policy = $this->policyService->getUserPreferenceForUserId($policyKey, $userId);

		/** @var LibresignUserPolicyResponse $data */
		$data = [
			'policy' => $this->serializeUserPolicy($userId, $policyKey, $policy),
		];

		return new DataResponse($data);
	}

	/**
	 * Save a system-level policy value
	 *
	 * @param string $policyKey Policy identifier to persist at the system layer.
	 * @param null|bool|int|float|string $value Policy value to persist. Null resets the policy to its default system value.
	 * @param bool $allowChildOverride Whether lower layers may override this system default.
	 * @return DataResponse<Http::STATUS_OK, LibresignSystemPolicyWriteResponse, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 400: Invalid policy value
	 */
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/policies/system/{policyKey}', requirements: ['apiVersion' => '(v1)', 'policyKey' => '[a-z0-9_]+'])]
	public function setSystem(string $policyKey, null|bool|int|float|string $value = null, bool $allowChildOverride = false): DataResponse {
		$value = $this->readScalarParam('value', $value);
		$allowChildOverride = $this->readBoolParam('allowChildOverride', $allowChildOverride);

		try {
			$policy = $this->policyService->saveSystem($policyKey, $value, $allowChildOverride);
			/** @var LibresignSystemPolicyWriteResponse $data */
			$data = [
				'message' => $this->l10n->t('Settings saved'),
				'policy' => $policy->toArray(),
			];

			return new DataResponse($data);
		} catch (\InvalidArgumentException $exception) {
			/** @var LibresignErrorResponse $data */
			$data = [
				'error' => $exception->getMessage(),
			];

			return new DataResponse($data, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Save a group-level policy value
	 *
	 * @param string $groupId Group identifier that receives the policy binding.
	 * @param string $policyKey Policy identifier to persist at the group layer.
	 * @param null|bool|int|float|string $value Policy value to persist for the group.
	 * @param bool $allowChildOverride Whether users and requests below this group may override the group default.
	 * @return DataResponse<Http::STATUS_OK, LibresignGroupPolicyWriteResponse, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, LibresignErrorResponse, array{}>|DataResponse<Http::STATUS_FORBIDDEN, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 400: Invalid policy value
	 * 403: Forbidden
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/policies/group/{groupId}/{policyKey}', requirements: ['apiVersion' => '(v1)', 'groupId' => '[^/]+', 'policyKey' => '[a-z0-9_]+'])]
	public function setGroup(string $groupId, string $policyKey, null|bool|int|float|string $value = null, bool $allowChildOverride = false): DataResponse {
		if (!$this->canManageGroupPolicy($groupId)) {
			return $this->forbiddenGroupPolicyResponse();
		}

		$value = $this->readScalarParam('value', $value);
		$allowChildOverride = $this->readBoolParam('allowChildOverride', $allowChildOverride);

		try {
			$policy = $this->policyService->saveGroupPolicy($policyKey, $groupId, $value, $allowChildOverride);
			/** @var LibresignGroupPolicyWriteResponse $data */
			$data = [
				'message' => $this->l10n->t('Settings saved'),
				'policy' => $this->serializeGroupPolicy($groupId, $policyKey, $policy),
			];

			return new DataResponse($data);
		} catch (\DomainException $exception) {
			/** @var LibresignErrorResponse $data */
			$data = [
				'error' => $exception->getMessage(),
			];

			return new DataResponse($data, Http::STATUS_FORBIDDEN);
		} catch (\InvalidArgumentException $exception) {
			/** @var LibresignErrorResponse $data */
			$data = [
				'error' => $exception->getMessage(),
			];

			return new DataResponse($data, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Clear a group-level policy value
	 *
	 * @param string $groupId Group identifier that receives the policy binding.
	 * @param string $policyKey Policy identifier to clear for the selected group.
	 * @return DataResponse<Http::STATUS_OK, LibresignGroupPolicyWriteResponse, array{}>|DataResponse<Http::STATUS_FORBIDDEN, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 403: Forbidden
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/policies/group/{groupId}/{policyKey}', requirements: ['apiVersion' => '(v1)', 'groupId' => '[^/]+', 'policyKey' => '[a-z0-9_]+'])]
	public function clearGroup(string $groupId, string $policyKey): DataResponse {
		if (!$this->canManageGroupPolicy($groupId)) {
			return $this->forbiddenGroupPolicyResponse();
		}

		try {
			$policy = $this->policyService->clearGroupPolicy($policyKey, $groupId);
			/** @var LibresignGroupPolicyWriteResponse $data */
			$data = [
				'message' => $this->l10n->t('Settings saved'),
				'policy' => $this->serializeGroupPolicy($groupId, $policyKey, $policy),
			];

			return new DataResponse($data);
		} catch (\DomainException $exception) {
			/** @var LibresignErrorResponse $data */
			$data = [
				'error' => $exception->getMessage(),
			];

			return new DataResponse($data, Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Save a user policy preference
	 *
	 * @param string $policyKey Policy identifier to persist for the current user.
	 * @param null|bool|int|float|string $value Policy value to persist as the current user's default.
	 * @return DataResponse<Http::STATUS_OK, LibresignSystemPolicyWriteResponse, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 400: Invalid policy value
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/policies/user/{policyKey}', requirements: ['apiVersion' => '(v1)', 'policyKey' => '[a-z0-9_]+'])]
	public function setUserPreference(string $policyKey, null|bool|int|float|string $value = null): DataResponse {
		$value = $this->readScalarParam('value', $value);

		try {
			$policy = $this->policyService->saveUserPreference($policyKey, $value);
			/** @var LibresignSystemPolicyWriteResponse $data */
			$data = [
				'message' => $this->l10n->t('Settings saved'),
				'policy' => $policy->toArray(),
			];

			return new DataResponse($data);
		} catch (\InvalidArgumentException $exception) {
			/** @var LibresignErrorResponse $data */
			$data = [
				'error' => $exception->getMessage(),
			];

			return new DataResponse($data, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Save a user policy preference for a target user (admin scope)
	 *
	 * @param string $userId Target user identifier that receives the policy preference.
	 * @param string $policyKey Policy identifier to persist for the target user.
	 * @param null|bool|int|float|string $value Policy value to persist as target user preference.
	 * @return DataResponse<Http::STATUS_OK, LibresignUserPolicyWriteResponse, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, LibresignErrorResponse, array{}>|DataResponse<Http::STATUS_FORBIDDEN, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 400: Invalid policy value
	 * 403: Forbidden
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/policies/user/{userId}/{policyKey}', requirements: ['apiVersion' => '(v1)', 'userId' => '[^/]+', 'policyKey' => '[a-z0-9_]+'])]
	public function setUserPolicyForUser(string $userId, string $policyKey, null|bool|int|float|string $value = null): DataResponse {
		if (!$this->canManageUserPolicy($userId)) {
			return $this->forbiddenUserPolicyResponse();
		}

		$value = $this->readScalarParam('value', $value);

		try {
			$policy = $this->policyService->saveUserPreferenceForUserId($policyKey, $userId, $value);
			/** @var LibresignUserPolicyWriteResponse $data */
			$data = [
				'message' => $this->l10n->t('Settings saved'),
				'policy' => $this->serializeUserPolicy($userId, $policyKey, $policy),
			];

			return new DataResponse($data);
		} catch (\InvalidArgumentException $exception) {
			/** @var LibresignErrorResponse $data */
			$data = [
				'error' => $exception->getMessage(),
			];

			return new DataResponse($data, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Clear a user policy preference
	 *
	 * @param string $policyKey Policy identifier to clear for the current user.
	 * @return DataResponse<Http::STATUS_OK, LibresignSystemPolicyWriteResponse, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/policies/user/{policyKey}', requirements: ['apiVersion' => '(v1)', 'policyKey' => '[a-z0-9_]+'])]
	public function clearUserPreference(string $policyKey): DataResponse {
		$policy = $this->policyService->clearUserPreference($policyKey);
		/** @var LibresignSystemPolicyWriteResponse $data */
		$data = [
			'message' => $this->l10n->t('Settings saved'),
			'policy' => $policy->toArray(),
		];

		return new DataResponse($data);
	}

	/**
	 * Clear a user policy preference for a target user (admin scope)
	 *
	 * @param string $userId Target user identifier that receives the policy preference removal.
	 * @param string $policyKey Policy identifier to clear for the target user.
	 * @return DataResponse<Http::STATUS_OK, LibresignUserPolicyWriteResponse, array{}>|DataResponse<Http::STATUS_FORBIDDEN, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 403: Forbidden
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/policies/user/{userId}/{policyKey}', requirements: ['apiVersion' => '(v1)', 'userId' => '[^/]+', 'policyKey' => '[a-z0-9_]+'])]
	public function clearUserPolicyForUser(string $userId, string $policyKey): DataResponse {
		if (!$this->canManageUserPolicy($userId)) {
			return $this->forbiddenUserPolicyResponse();
		}

		$policy = $this->policyService->clearUserPreferenceForUserId($policyKey, $userId);
		/** @var LibresignUserPolicyWriteResponse $data */
		$data = [
			'message' => $this->l10n->t('Settings saved'),
			'policy' => $this->serializeUserPolicy($userId, $policyKey, $policy),
		];

		return new DataResponse($data);
	}

	/** @return LibresignGroupPolicyState */
	private function serializeGroupPolicy(string $groupId, string $policyKey, ?PolicyLayer $policy): array {
		return [
			'policyKey' => $policyKey,
			'scope' => 'group',
			'targetId' => $groupId,
			'value' => $policy?->getValue(),
			'allowChildOverride' => $policy?->isAllowChildOverride() ?? true,
			'visibleToChild' => $policy?->isVisibleToChild() ?? true,
			'allowedValues' => $policy?->getAllowedValues() ?? [],
		];
	}

	/** @return LibresignUserPolicyState */
	private function serializeUserPolicy(string $userId, string $policyKey, ?PolicyLayer $policy): array {
		return [
			'policyKey' => $policyKey,
			'scope' => 'user',
			'targetId' => $userId,
			'value' => $policy?->getValue(),
		];
	}

	private function canManageGroupPolicy(string $groupId): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}

		if ($this->groupManager->isAdmin($user->getUID())) {
			return true;
		}

		$group = $this->groupManager->get($groupId);
		if ($group === null) {
			return false;
		}

		return $this->subAdmin->isSubAdminOfGroup($user, $group);
	}

	private function canManageUserPolicy(string $userId): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
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
	 * @return array<string, array{groupCount: int, userCount: int}>
	 */
	private function resolveRuleCountsForActor(?IUser $user): array {
		if ($user === null) {
			return [];
		}

		if ($this->groupManager->isAdmin($user->getUID())) {
			$groupIds = array_values(array_map(
				static fn ($group): string => $group->getGID(),
				$this->groupManager->search(''),
			));
			$userIds = array_values(array_map(
				static fn ($candidate): string => $candidate->getUID(),
				$this->userManager->searchDisplayName(''),
			));

			return $this->policyService->getRuleCounts($groupIds, $userIds);
		}

		if ($this->subAdmin->isSubAdmin($user)) {
			$groupIds = array_map(
				static fn ($group) => $group->getGID(),
				$this->subAdmin->getSubAdminsGroups($user),
			);
			return $this->policyService->getRuleCounts($groupIds, []);
		}

		return [];
	}

	private function readScalarParam(string $key, null|bool|int|float|string $default): null|bool|int|float|string {
		$value = $this->request->getParams()[$key] ?? $default;
		if (!is_scalar($value) && $value !== null) {
			return $default;
		}

		return $value;
	}

	private function readBoolParam(string $key, bool $default): bool {
		$value = $this->request->getParams()[$key] ?? $default;
		return is_bool($value) ? $value : $default;
	}

	/** @return DataResponse<Http::STATUS_FORBIDDEN, LibresignErrorResponse, array{}> */
	private function forbiddenGroupPolicyResponse(): DataResponse {
		/** @var LibresignErrorResponse $data */
		$data = [
			'error' => $this->l10n->t('Not allowed to manage this group policy'),
		];

		return new DataResponse($data, Http::STATUS_FORBIDDEN);
	}

	/** @return DataResponse<Http::STATUS_FORBIDDEN, LibresignErrorResponse, array{}> */
	private function forbiddenUserPolicyResponse(): DataResponse {
		/** @var LibresignErrorResponse $data */
		$data = [
			'error' => $this->l10n->t('Not allowed to manage this user policy'),
		];

		return new DataResponse($data, Http::STATUS_FORBIDDEN);
	}
}
