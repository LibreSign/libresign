<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCA\Libresign\Service\ConfigureCheckService;
use OCA\Libresign\Service\InstallService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

class AdminController extends Controller {
	public function __construct(
		IRequest $request,
		private ConfigureCheckService $configureCheckService,
		private InstallService $installService,
		private CertificateEngineHandler $certificateEngineHandler
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoCSRFRequired]
	public function generateCertificateCfssl(
		array $rootCert,
		string $cfsslUri = '',
		string $configPath = ''
	): DataResponse {
		return $this->generateCertificate($rootCert, [
			'engine' => 'cfssl',
			'configPath' => trim($configPath),
			'cfsslUri' => trim($cfsslUri),
		]);
	}

	#[NoCSRFRequired]
	public function generateCertificateOpenSsl(
		array $rootCert,
		string $configPath = ''
	): DataResponse {
		return $this->generateCertificate($rootCert, [
			'engine' => 'openssl',
			'configPath' => trim($configPath),
		]);
	}

	private function generateCertificate(
		array $rootCert,
		array $properties = [],
	): DataResponse {
		try {
			$names = [];
			foreach ($rootCert['names'] as $item) {
				$names[$item['id']]['value'] = $this->trimAndThrowIfEmpty($item['id'], $item['value']);
			}
			$this->installService->generate(
				$this->trimAndThrowIfEmpty('commonName', $rootCert['commonName']),
				$names ?? [],
				$properties,
			);

			return new DataResponse([
				'data' => $this->certificateEngineHandler->getEngine()->toArray(),
			]);
		} catch (\Exception $exception) {
			return new DataResponse(
				[
					'message' => $exception->getMessage()
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	#[NoCSRFRequired]
	public function loadCertificate(): DataResponse {
		$engine = $this->certificateEngineHandler->getEngine();
		$certificate = $engine->toArray();
		$configureResult = $engine->configureCheck();
		$success = array_filter(
			$configureResult,
			function (ConfigureCheckHelper $config) {
				return $config->getStatus() === 'success';
			}
		);
		$certificate['generated'] = count($success) === count($configureResult);

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
			$this->installService->installPdftk($async);
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

			return new DataResponse([]);
		} catch (\Exception $exception) {
			return new DataResponse(
				[
					'message' => $exception->getMessage()
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	public function downloadStatus(): DataResponse {
		$return = $this->installService->getTotalSize();
		return new DataResponse($return);
	}

	public function configureCheck(): DataResponse {
		return new DataResponse(
			$this->configureCheckService->checkAll()
		);
	}
}
