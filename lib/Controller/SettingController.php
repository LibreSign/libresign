<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\SignatureService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class SettingController extends Controller {
	public function __construct(
		IRequest $request,
		private SignatureService $signatureService
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function hasRootCert(): DataResponse {
		$checkData = [
			'hasRootCert' => $this->signatureService->hasRootCert()
		];

		return new DataResponse($checkData);
	}
}
