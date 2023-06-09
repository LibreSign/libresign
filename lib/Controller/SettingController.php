<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class SettingController extends Controller {
	public function __construct(
		IRequest $request,
		private CertificateEngineHandler $certificateEngineHandler
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function hasRootCert(): DataResponse {
		$checkData = [
			'hasRootCert' => $this->certificateEngineHandler->getEngine()->isSetupOk()
		];

		return new DataResponse($checkData);
	}
}
