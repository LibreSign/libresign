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
use OCP\IRequest;

/**
 * @psalm-import-type LibresignEffectivePoliciesResponse from \OCA\Libresign\ResponseDefinitions
 */
final class PolicyController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
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
		$policies = [];
		foreach ($this->policyService->resolveKnownPolicies() as $policyKey => $resolvedPolicy) {
			$policies[$policyKey] = $resolvedPolicy->toArray();
		}

		return new DataResponse([
			'policies' => $policies,
		]);
	}
}
