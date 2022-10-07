<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\AdminSignatureService;
use OCA\Libresign\Service\ConfigureCheckService;
use OCA\Libresign\Service\InstallService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class AdminController extends Controller {
	/** @var AdminSignatureService */
	private $adminSignatureservice;
	/** @var ConfigureCheckService */
	private $configureCheckService;
	/** @var InstallService */
	private $installService;

	public function __construct(
		IRequest $request,
		AdminSignatureService $adminSignatureService,
		ConfigureCheckService $configureCheckService,
		InstallService $installService
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->adminSignatureService = $adminSignatureService;
		$this->configureCheckService = $configureCheckService;
		$this->installService = $installService;
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
		string $cfsslUri = '',
		string $configPath = ''
	): DataResponse {
		try {
			$this->installService->generate(
				$this->trimAndThrowIfEmpty('commonName', $commonName),
				$this->trimAndThrowIfEmpty('country', $country),
				$this->trimAndThrowIfEmpty('organization', $organization),
				$this->trimAndThrowIfEmpty('organizationUnit', $organizationUnit),
				trim($configPath),
				trim($cfsslUri)
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

	private function trimAndThrowIfEmpty($key, $value): string {
		if (empty($value)) {
			throw new LibresignException("parameter '{$key}' is required!", 400);
		}
		return trim($value);
	}

	/**
	 * @NoCSRFRequired
	 */
	public function downloadBinaries(): DataResponse {
		try {
			$this->installService->installJava();
			$this->installService->installJSignPdf();
			$this->installService->installCfssl();
			$this->installService->installCli();
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
	 * @NoCSRFRequired
	 */
	public function configureCheck(): DataResponse {
		return new DataResponse(
			$this->configureCheckService->checkAll()
		);
	}
}
