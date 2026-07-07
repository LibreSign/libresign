<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * @psalm-import-type LibresignHasRootCertResponse from \OCA\Libresign\ResponseDefinitions
 */
class SettingController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private CertificateEngineFactory $certificateEngineFactory,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Has root certificate
	 *
	 * Checks whether the root certificate has been configured by checking the Nextcloud configuration table to see if the root certificate settings have
	 *
	 * @return DataResponse<Http::STATUS_OK, LibresignHasRootCertResponse, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/setting/has-root-cert', requirements: ['apiVersion' => '(v1)'])]
	public function hasRootCert(): DataResponse {
		$engine = $this->certificateEngineFactory->getEngine();
		$checkData = [
			'hasRootCert' => $engine->getName() !== 'none' && $engine->isSetupOk()
		];

		return new DataResponse($checkData);
	}
}
