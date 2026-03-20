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
use OCP\IUserSession;

/**
 * @psalm-import-type LibresignErrorResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignEffectivePolicyState from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignEffectivePoliciesResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignGroupPolicyResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignGroupPolicyState from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignGroupPolicyWriteResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignSystemPolicyWriteResponse from \OCA\Libresign\ResponseDefinitions
 */
final class PolicyController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private IL10N $l10n,
		private PolicyService $policyService,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
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
		/** @var array<string, LibresignEffectivePolicyState> $policies */
		$policies = [];
		foreach ($this->policyService->resolveKnownPolicies() as $policyKey => $resolvedPolicy) {
			/** @var LibresignEffectivePolicyState $policyState */
			$policyState = $resolvedPolicy->toArray();
			$policies[$policyKey] = $policyState;
		}

		/** @var LibresignEffectivePoliciesResponse $data */
		$data = [
			'policies' => $policies,
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
	 * Save a system-level policy value
	 *
	 * @param string $policyKey Policy identifier to persist at the system layer.
	 * @param null|bool|int|float|string $value Policy value to persist. Null resets the policy to its default system value.
	 * @param bool $allowChildOverride Whether lower layers may override this system default.
	 * @return DataResponse<Http::STATUS_OK, LibresignSystemPolicyWriteResponse, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, LibresignErrorResponse, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 400: Invalid policy value
	 * 500: Internal server error
	 */
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/policies/system/{policyKey}', requirements: ['apiVersion' => '(v1)', 'policyKey' => '[a-z0-9_]+'])]
	public function setSystem(string $policyKey, null|bool|int|float|string $value = null, bool $allowChildOverride = false): DataResponse {
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
				'error' => $this->l10n->t($exception->getMessage()),
			];

			return new DataResponse($data, Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $exception) {
			/** @var LibresignErrorResponse $data */
			$data = [
				'error' => $exception->getMessage(),
			];

			return new DataResponse($data, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Save a group-level policy value
	 *
	 * @param string $groupId Group identifier that receives the policy binding.
	 * @param string $policyKey Policy identifier to persist at the group layer.
	 * @param null|bool|int|float|string $value Policy value to persist for the group.
	 * @param bool $allowChildOverride Whether users and requests below this group may override the group default.
	 * @return DataResponse<Http::STATUS_OK, LibresignGroupPolicyWriteResponse, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, LibresignErrorResponse, array{}>|DataResponse<Http::STATUS_FORBIDDEN, LibresignErrorResponse, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 400: Invalid policy value
	 * 403: Forbidden
	 * 500: Internal server error
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/policies/group/{groupId}/{policyKey}', requirements: ['apiVersion' => '(v1)', 'groupId' => '[^/]+', 'policyKey' => '[a-z0-9_]+'])]
	public function setGroup(string $groupId, string $policyKey, null|bool|int|float|string $value = null, bool $allowChildOverride = false): DataResponse {
		if (!$this->canManageGroupPolicy($groupId)) {
			return $this->forbiddenGroupPolicyResponse();
		}

		try {
			$policy = $this->policyService->saveGroupPolicy($policyKey, $groupId, $value, $allowChildOverride);
			/** @var LibresignGroupPolicyWriteResponse $data */
			$data = [
				'message' => $this->l10n->t('Settings saved'),
				'policy' => $this->serializeGroupPolicy($groupId, $policyKey, $policy),
			];

			return new DataResponse($data);
		} catch (\InvalidArgumentException $exception) {
			/** @var LibresignErrorResponse $data */
			$data = [
				'error' => $this->l10n->t($exception->getMessage()),
			];

			return new DataResponse($data, Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $exception) {
			/** @var LibresignErrorResponse $data */
			$data = [
				'error' => $exception->getMessage(),
			];

			return new DataResponse($data, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Clear a group-level policy value
	 *
	 * @param string $groupId Group identifier that receives the policy binding.
	 * @param string $policyKey Policy identifier to clear for the selected group.
	 * @return DataResponse<Http::STATUS_OK, LibresignGroupPolicyWriteResponse, array{}>|DataResponse<Http::STATUS_FORBIDDEN, LibresignErrorResponse, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 403: Forbidden
	 * 500: Internal server error
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
		} catch (\Throwable $exception) {
			/** @var LibresignErrorResponse $data */
			$data = [
				'error' => $exception->getMessage(),
			];

			return new DataResponse($data, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Save a user policy preference
	 *
	 * @param string $policyKey Policy identifier to persist for the current user.
	 * @param null|bool|int|float|string $value Policy value to persist as the current user's default.
	 * @return DataResponse<Http::STATUS_OK, LibresignSystemPolicyWriteResponse, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, LibresignErrorResponse, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 400: Invalid policy value
	 * 500: Internal server error
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/policies/user/{policyKey}', requirements: ['apiVersion' => '(v1)', 'policyKey' => '[a-z0-9_]+'])]
	public function setUserPreference(string $policyKey, null|bool|int|float|string $value = null): DataResponse {
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
				'error' => $this->l10n->t($exception->getMessage()),
			];

			return new DataResponse($data, Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $exception) {
			/** @var LibresignErrorResponse $data */
			$data = [
				'error' => $exception->getMessage(),
			];

			return new DataResponse($data, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Save a user policy preference for a target user (admin scope)
	 *
	 * @param string $userId Target user identifier that receives the policy preference.
	 * @param string $policyKey Policy identifier to persist for the target user.
	 * @param null|bool|int|float|string $value Policy value to persist as target user preference.
	 * @return DataResponse<Http::STATUS_OK, LibresignSystemPolicyWriteResponse, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, LibresignErrorResponse, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 400: Invalid policy value
	 * 500: Internal server error
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/policies/user/{userId}/{policyKey}', requirements: ['apiVersion' => '(v1)', 'userId' => '[^/]+', 'policyKey' => '[a-z0-9_]+'])]
	public function setUserPolicyForUser(string $userId, string $policyKey, null|bool|int|float|string $value = null): DataResponse {
		try {
			$policy = $this->policyService->saveUserPreferenceForUserId($policyKey, $userId, $value);
			/** @var LibresignSystemPolicyWriteResponse $data */
			$data = [
				'message' => $this->l10n->t('Settings saved'),
				'policy' => $policy->toArray(),
			];

			return new DataResponse($data);
		} catch (\InvalidArgumentException $exception) {
			/** @var LibresignErrorResponse $data */
			$data = [
				'error' => $this->l10n->t($exception->getMessage()),
			];

			return new DataResponse($data, Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $exception) {
			/** @var LibresignErrorResponse $data */
			$data = [
				'error' => $exception->getMessage(),
			];

			return new DataResponse($data, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Clear a user policy preference
	 *
	 * @param string $policyKey Policy identifier to clear for the current user.
	 * @return DataResponse<Http::STATUS_OK, LibresignSystemPolicyWriteResponse, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 500: Internal server error
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/policies/user/{policyKey}', requirements: ['apiVersion' => '(v1)', 'policyKey' => '[a-z0-9_]+'])]
	public function clearUserPreference(string $policyKey): DataResponse {
		try {
			$policy = $this->policyService->clearUserPreference($policyKey);
			/** @var LibresignSystemPolicyWriteResponse $data */
			$data = [
				'message' => $this->l10n->t('Settings saved'),
				'policy' => $policy->toArray(),
			];

			return new DataResponse($data);
		} catch (\Throwable $exception) {
			/** @var LibresignErrorResponse $data */
			$data = [
				'error' => $exception->getMessage(),
			];

			return new DataResponse($data, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Clear a user policy preference for a target user (admin scope)
	 *
	 * @param string $userId Target user identifier that receives the policy preference removal.
	 * @param string $policyKey Policy identifier to clear for the target user.
	 * @return DataResponse<Http::STATUS_OK, LibresignSystemPolicyWriteResponse, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 500: Internal server error
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/policies/user/{userId}/{policyKey}', requirements: ['apiVersion' => '(v1)', 'userId' => '[^/]+', 'policyKey' => '[a-z0-9_]+'])]
	public function clearUserPolicyForUser(string $userId, string $policyKey): DataResponse {
		try {
			$policy = $this->policyService->clearUserPreferenceForUserId($policyKey, $userId);
			/** @var LibresignSystemPolicyWriteResponse $data */
			$data = [
				'message' => $this->l10n->t('Settings saved'),
				'policy' => $policy->toArray(),
			];

			return new DataResponse($data);
		} catch (\Throwable $exception) {
			/** @var LibresignErrorResponse $data */
			$data = [
				'error' => $exception->getMessage(),
			];

			return new DataResponse($data, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
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

	/** @return DataResponse<Http::STATUS_FORBIDDEN, LibresignErrorResponse, array{}> */
	private function forbiddenGroupPolicyResponse(): DataResponse {
		/** @var LibresignErrorResponse $data */
		$data = [
			'error' => $this->l10n->t('Not allowed to manage this group policy'),
		];

		return new DataResponse($data, Http::STATUS_FORBIDDEN);
	}
}
