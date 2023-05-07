<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\SignatureService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class SettingController extends Controller {
	public function __construct(
		IRequest $request,
		private SignatureService $signatureService
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @todo remove NoCSRFRequired
	 * @return DataResponse
	 */
	public function hasRootCert() {
		$checkData = [
			'hasRootCert' => $this->signatureService->hasRootCert()
		];

		return new DataResponse($checkData);
	}
}
