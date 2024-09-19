<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\AEngineHandler;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\Install\ConfigureCheckService;
use OCA\Libresign\Service\Install\InstallService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IEventSource;
use OCP\IEventSourceFactory;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;

/**
 * @psalm-import-type LibresignEngineHandler from ResponseDefinitions
 * @psalm-import-type LibresignCetificateDataGenerated from ResponseDefinitions
 * @psalm-import-type LibresignConfigureCheck from ResponseDefinitions
 * @psalm-import-type LibresignRootCertificate from ResponseDefinitions
 */
class AdminController extends AEnvironmentAwareController {
	private IEventSource $eventSource;
	public function __construct(
		IRequest $request,
		private IAppConfig $appConfig,
		private ConfigureCheckService $configureCheckService,
		private InstallService $installService,
		private CertificateEngineHandler $certificateEngineHandler,
		private IEventSourceFactory $eventSourceFactory,
		private IL10N $l10n,
		protected ISession $session,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->eventSource = $this->eventSourceFactory->create();
	}

	/**
	 * Generate certificate using CFSSL engine
	 *
	 * @param array{commonName: string, names: array<string, array{value:string}>} $rootCert fields of root certificate
	 * @param string $cfsslUri URI of CFSSL API
	 * @param string $configPath Path of config files of CFSSL
	 * @return DataResponse<Http::STATUS_OK, array{data: LibresignEngineHandler}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 401: Account not found
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/certificate/cfssl', requirements: ['apiVersion' => '(v1)'])]
	public function generateCertificateCfssl(
		array $rootCert,
		string $cfsslUri = '',
		string $configPath = '',
	): DataResponse {
		try {
			$engineHandler = $this->generateCertificate($rootCert, [
				'engine' => 'cfssl',
				'configPath' => trim($configPath),
				'cfsslUri' => trim($cfsslUri),
			])->toArray();
			return new DataResponse([
				'data' => $engineHandler,
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

	/**
	 * Generate certificate using OpenSSL engine
	 *
	 * @param array{commonName: string, names: array<string, array{value:string}>} $rootCert fields of root certificate
	 * @param string $configPath Path of config files of CFSSL
	 * @return DataResponse<Http::STATUS_OK, array{data: LibresignEngineHandler}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 401: Account not found
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/certificate/openssl', requirements: ['apiVersion' => '(v1)'])]
	public function generateCertificateOpenSsl(
		array $rootCert,
		string $configPath = '',
	): DataResponse {
		try {
			$engineHandler = $this->generateCertificate($rootCert, [
				'engine' => 'openssl',
				'configPath' => trim($configPath),
			])->toArray();
			return new DataResponse([
				'data' => $engineHandler,
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

	private function generateCertificate(
		array $rootCert,
		array $properties = [],
	): AEngineHandler {
		$names = [];
		foreach ($rootCert['names'] as $item) {
			if (empty($item['id'])) {
				throw new LibresignException('Parameter id is required!', 400);
			}
			$names[$item['id']]['value'] = $this->trimAndThrowIfEmpty($item['id'], $item['value']);
		}
		$this->installService->generate(
			$this->trimAndThrowIfEmpty('commonName', $rootCert['commonName']),
			$names,
			$properties,
		);

		return $this->certificateEngineHandler->getEngine();
	}

	/**
	 * Load certificate data
	 *
	 * Return all data of root certificate and a field called `generated` with a boolean value.
	 *
	 * @return DataResponse<Http::STATUS_OK, LibresignCetificateDataGenerated, array{}>
	 *
	 * 200: OK
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/certificate', requirements: ['apiVersion' => '(v1)'])]
	public function loadCertificate(): DataResponse {
		$engine = $this->certificateEngineHandler->getEngine();
		/** @var LibresignEngineHandler */
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

	/**
	 * Check the configuration of LibreSign
	 *
	 * Return the status of necessary configuration and tips to fix the problems.
	 *
	 * @return DataResponse<Http::STATUS_OK, LibresignConfigureCheck[], array{}>
	 *
	 * 200: OK
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/configure-check', requirements: ['apiVersion' => '(v1)'])]
	public function configureCheck(): DataResponse {
		/** @var LibresignConfigureCheck[] */
		$configureCheckList = $this->configureCheckService->checkAll();
		return new DataResponse(
			$configureCheckList
		);
	}

	/**
	 * Disable hate limit to current session
	 *
	 * This will disable hate limit to current session.
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: OK
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/disable-hate-limit', requirements: ['apiVersion' => '(v1)'])]
	public function disableHateLimit(): DataResponse {
		$this->session->set('app_api', true);

		// TODO: Remove after drop support NC29
		// deprecated since AppAPI 2.8.0
		$this->session->set('app_api_system', true);

		return new DataResponse();
	}

	/**
	 * @IgnoreOpenAPI
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/install-and-validate', requirements: ['apiVersion' => '(v1)'])]
	public function installAndValidate(): void {
		try {
			$async = \function_exists('proc_open');
			$this->installService->installJava($async);
			$this->installService->installJSignPdf($async);
			$this->installService->installPdftk($async);
			if ($this->appConfig->getAppValue('certificate_engine') === 'cfssl') {
				$this->installService->installCfssl($async);
			}

			$this->configureCheckService->disableCache();
			$this->eventSource->send('configure_check', $this->configureCheckService->checkAll());
			$seconds = 0;
			while ($this->installService->isDownloadWip()) {
				$totalSize = $this->installService->getTotalSize();
				$this->eventSource->send('total_size', json_encode($totalSize));
				if ($errors = $this->installService->getErrorMessages()) {
					$this->eventSource->send('errors', json_encode($errors));
				}
				usleep(200000); // 0.2 seconds
				$seconds += 0.2;
				if ($seconds === 5.0) {
					$this->eventSource->send('configure_check', $this->configureCheckService->checkAll());
					$seconds = 0;
				}
			}
			if ($errors = $this->installService->getErrorMessages()) {
				$this->eventSource->send('errors', json_encode($errors));
			}
		} catch (\Exception $exception) {
			$this->eventSource->send('errors', json_encode([
				$this->l10n->t('Could not download binaries.'),
				$exception->getMessage(),
			]));
		}

		$this->eventSource->send('configure_check', $this->configureCheckService->checkAll());
		$this->eventSource->send('done', '');
		$this->eventSource->close();
		// Nextcloud inject a lot of headers that is incompatible with SSE
		exit();
	}

}
