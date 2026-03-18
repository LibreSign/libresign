<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Policy\PolicyService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;

/**
 * @psalm-import-type LibresignErrorResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignEffectivePolicyState from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignEffectivePoliciesResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignSystemPolicyWriteResponse from \OCA\Libresign\ResponseDefinitions
 */
final class PolicyController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private IL10N $l10n,
		private PolicyService $policyService,
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
	 * Save a system-level policy value
	 *
	 * @param string $policyKey Policy identifier to persist at the system layer.
	 * @param null|bool|int|float|string $value Policy value to persist. Null resets the policy to its default system value.
	 * @return DataResponse<Http::STATUS_OK, LibresignSystemPolicyWriteResponse, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, LibresignErrorResponse, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 400: Invalid policy value
	 * 500: Internal server error
	 */
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/policies/system/{policyKey}', requirements: ['apiVersion' => '(v1)', 'policyKey' => '[a-z0-9_]+'])]
	public function setSystem(string $policyKey, null|bool|int|float|string $value = null): DataResponse {
		try {
			$policy = $this->policyService->saveSystem($policyKey, $value);
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
}
