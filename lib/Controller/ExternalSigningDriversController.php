<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\Signing\Provider\ExternalSigningDriverCatalog;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type LibresignErrorResponse from ResponseDefinitions
 * @psalm-import-type LibresignExternalSigningDriversResponse from ResponseDefinitions
 */
final class ExternalSigningDriversController extends AEnvironmentAwareOCSController {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private ExternalSigningDriverCatalog $catalog,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * List registered external signing driver types for system administrators.
	 *
	 * This endpoint does not expose configured integration instances or secrets.
	 *
	 * @return DataResponse<Http::STATUS_OK, LibresignExternalSigningDriversResponse, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, LibresignErrorResponse, array{}>|DataResponse<Http::STATUS_FORBIDDEN, LibresignErrorResponse, array{}>|DataResponse<Http::STATUS_NOT_FOUND, LibresignErrorResponse, array{}>
	 *
	 * 200: Registered experimental driver definitions
	 * 401: Authentication required
	 * 403: System administrator required
	 * 404: Experimental features disabled
	 */
	#[OpenAPI(tags: ['system'])]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/signing/drivers', requirements: ['apiVersion' => '(v1)'])]
	public function index(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
		}
		if (!$this->groupManager->isAdmin($user->getUID())) {
			return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}
		if (!$this->catalog->isEnabled()) {
			return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
		}

		/** @var LibresignExternalSigningDriversResponse $data */
		$data = ['drivers' => $this->catalog->listDrivers()];
		return new DataResponse($data);
	}
}
