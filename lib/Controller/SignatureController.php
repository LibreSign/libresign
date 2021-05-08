<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\SignatureService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class SignatureController extends Controller {
	use HandleErrorsTrait;
	use HandleParamsTrait;

	/** @var SignatureService */
	private $signatureService;

	/** @var string */
	private $userId;

	public function __construct(
		IRequest $request,
		SignatureService $signatureService,
		$userId
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->signatureService = $signatureService;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @todo remove NoCSRFRequired
	 */
	public function hasRootCert() {
		try {
			$checkData = $this->signatureService->hasRootCert();

			return new DataResponse($checkData);
		} catch (\Exception $exception) {
			return $this->handleErrors($exception);
		}
	}
}
