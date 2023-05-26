<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCA\Libresign\Service\AdminSignatureService;
use OCA\Libresign\Service\ConfigureCheckService;
use OCA\Libresign\Service\InstallService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

class AdminController extends Controller {
	public function __construct(
		IRequest $request,
		private AdminSignatureService $adminSignatureService,
		private ConfigureCheckService $configureCheckService,
		private InstallService $installService
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function generateCertificate(
		array $rootCert,
		string $cfsslUri = '',
		string $configPath = ''
	): DataResponse {
		try {
			foreach ($rootCert['names'] as $key => $name) {
				$rootCert['names'][$key]['value'] = $this->trimAndThrowIfEmpty($key, $rootCert['names'][$key]['value']);
			}
			$this->installService->generate(
				$this->trimAndThrowIfEmpty('commonName', $rootCert['commonName']),
				$rootCert['names'],
				trim($configPath),
				trim($cfsslUri)
			);

			return new DataResponse([
				'success' => true,
				'data' => $this->adminSignatureService->loadKeys(),
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

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function loadCertificate(): DataResponse {
		$certificate = $this->adminSignatureService->loadKeys();
		$cfssl = $this->configureCheckService->checkCfsslConfigure();
		$success = array_filter(
			$cfssl,
			function (ConfigureCheckHelper $config) {
				return $config->getStatus() === 'success';
			}
		);
		$certificate['generated'] = count($success) === count($cfssl);

		return new DataResponse($certificate);
	}

	private function trimAndThrowIfEmpty(string $key, $value): string {
		if (empty($value)) {
			throw new LibresignException("parameter '{$key}' is required!", 400);
		}
		return trim($value);
	}

	#[NoCSRFRequired]
	public function downloadBinaries(): Response {
		try {
			$async = \function_exists('proc_open');
			$this->installService->installJava($async);
			$this->installService->installJSignPdf($async);
			$this->installService->installCfssl($async);
			$previous = [];
			do {
				$totalSize = $this->installService->getTotalSize();
				if (count($previous) === 10) {
					// with the same size
					if (!count($totalSize) || array_sum($previous) / count($previous) === $totalSize) {
						break;
					}
					array_shift($previous);
				}
				$previous[] = $totalSize;
				sleep(1);
			} while (true);

			return new DataResponse(
				[
					'success' => true,
				],
			);
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

	#[NoAdminRequired]
	public function downloadStatus(): DataResponse {
		$return = $this->installService->getTotalSize();
		return new DataResponse($return);
	}

	#[NoAdminRequired]
	public function configureCheck(): DataResponse {
		return new DataResponse(
			$this->configureCheckService->checkAll()
		);
	}
}
