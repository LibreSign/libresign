<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class SettingController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private CertificateEngineHandler $certificateEngineHandler
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function hasRootCert(): JSONResponse {
		$checkData = [
			'hasRootCert' => $this->certificateEngineHandler->getEngine()->isSetupOk()
		];

		return new JSONResponse($checkData);
	}
}
