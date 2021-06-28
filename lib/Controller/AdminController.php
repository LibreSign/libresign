<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\AdminSignatureService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class AdminController extends Controller {
	use HandleParamsTrait;

	/** @var AdminSignatureService */
	private $adminSignatureservice;

	public function __construct(
		IRequest $request,
		AdminSignatureService $adminSignatureService
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->adminSignatureService = $adminSignatureService;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function generateCertificate(
		string $commonName = null,
		string $country = null,
		string $organization = null,
		string $organizationUnit = null,
		string $cfsslUri = null,
		string $configPath = null
	): DataResponse {
		try {
			$this->checkParams([
				'commonName' => $commonName,
				'country' => $country,
				'organization' => $organization,
				'organizationUnit' => $organizationUnit,
				'cfsslUri' => $cfsslUri,
				'configPath' => $configPath
			]);

			$this->adminSignatureService->generate(
				$commonName,
				$country,
				$organization,
				$organizationUnit,
				$cfsslUri,
				$configPath
			);

			return new DataResponse([
				'success' => true
			]);
		} catch (\Exception $exception) {
			return new DataResponse(
				[
					'success' => false,
					'message' => $exception->getMessage()
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function loadCertificate(): DataResponse {
		$certificate = $this->adminSignatureService->loadKeys();

		return new DataResponse($certificate);
	}
}
