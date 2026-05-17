<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Controller\Traits\UploadValidator;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Service\Certificate\ValidateService;
use OCA\Libresign\Service\CertificatePolicyService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Install\ConfigureCheckService;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\IAppConfig;
use OCP\IEventSource;
use OCP\IEventSourceFactory;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use UnexpectedValueException;

/**
 * @psalm-import-type LibresignCertificateDataGenerated from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignCertificateEngineConfigResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignCertificatePolicyResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignConfigureCheck from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignConfigureChecksResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignEngineHandlerResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignErrorResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignErrorStatusResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignEngineHandler from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignIdentifyMethodSetting from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignMessageResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignSuccessStatusResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignFailureStatusResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignActiveSigningsResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignRootCertificate from \OCA\Libresign\ResponseDefinitions
 */
class AdminController extends AEnvironmentAwareController {
	use UploadValidator;

	private IEventSource $eventSource;
	public function __construct(
		IRequest $request,
		private IAppConfig $appConfig,
		private ConfigureCheckService $configureCheckService,
		private InstallService $installService,
		private CertificateEngineFactory $certificateEngineFactory,
		private IEventSourceFactory $eventSourceFactory,
		protected IL10N $l10n,
		protected ISession $session,
		private SignatureBackgroundService $signatureBackgroundService,
		private CertificatePolicyService $certificatePolicyService,
		private ValidateService $validateService,
		private IdentifyMethodService $identifyMethodService,
		private FileMapper $fileMapper,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->eventSource = $this->eventSourceFactory->create();
	}

	/**
	 * Generate certificate using CFSSL engine
	 *
	 * @param array{commonName: string, names: array<string, array{value:string|array<string>}>} $rootCert fields of root certificate
	 * @param string $cfsslUri URI of CFSSL API
	 * @param string $configPath Path of config files of CFSSL
	 * @return DataResponse<Http::STATUS_OK, LibresignEngineHandlerResponse, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, LibresignMessageResponse, array{}>
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
	 * @param array{commonName: string, names: array<string, array{value:string|array<string>}>} $rootCert fields of root certificate
	 * @param string $configPath Path of config files of CFSSL
	 * @return DataResponse<Http::STATUS_OK, LibresignEngineHandlerResponse, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, LibresignMessageResponse, array{}>
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

	/**
	 * Set certificate engine
	 *
	 * Sets the certificate engine (openssl, cfssl, or none) and automatically configures identify_methods when needed
	 *
	 * @param string $engine The certificate engine to use (openssl, cfssl, or none)
	 * @return DataResponse<Http::STATUS_OK, LibresignCertificateEngineConfigResponse, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, LibresignMessageResponse, array{}>
	 *
	 * 200: OK
	 * 400: Invalid engine
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/certificate/engine', requirements: ['apiVersion' => '(v1)'])]
	public function setCertificateEngine(string $engine): DataResponse {
		$validEngines = ['openssl', 'cfssl', 'none'];
		if (!in_array($engine, $validEngines, true)) {
			return new DataResponse(
				['message' => 'Invalid engine. Must be one of: ' . implode(', ', $validEngines)],
				Http::STATUS_BAD_REQUEST
			);
		}

		$handler = $this->certificateEngineFactory->getEngine();
		$handler->setEngine($engine);
		$identifyMethods = $this->identifyMethodService->getIdentifyMethodsSettings();

		return new DataResponse([
			'engine' => $engine,
			'identify_methods' => $identifyMethods,
		]);
	}

	private function generateCertificate(
		array $rootCert,
		array $properties = [],
	): IEngineHandler {
		$names = [];
		if (isset($rootCert['names'])) {
			$this->validateService->validateNames($rootCert['names']);
			foreach ($rootCert['names'] as $item) {
				if (is_array($item['value'])) {
					$trimmedValues = array_map('trim', $item['value']);
					$names[$item['id']]['value'] = array_filter($trimmedValues, fn ($val) => $val !== '');
				} else {
					$names[$item['id']]['value'] = trim((string)$item['value']);
				}
			}
		}
		$this->validateService->validate('CN', $rootCert['commonName']);
		$this->installService->generate(
			trim((string)$rootCert['commonName']),
			$properties['engine'],
			$names,
			$properties,
		);

		return $this->certificateEngineFactory->getEngine();
	}

	/**
	 * Load certificate data
	 *
	 * Return all data of root certificate and a field called `generated` with a boolean value.
	 *
	 * @return DataResponse<Http::STATUS_OK, LibresignCertificateDataGenerated, array{}>
	 *
	 * 200: OK
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/certificate', requirements: ['apiVersion' => '(v1)'])]
	public function loadCertificate(): DataResponse {
		$engine = $this->certificateEngineFactory->getEngine();
		/** @var LibresignCertificateDataGenerated */
		$certificate = $engine->toArray();

		return new DataResponse($certificate);
	}

	/**
	 * Check the configuration of LibreSign
	 *
	 * Return the status of necessary configuration and tips to fix the problems.
	 *
	 * @return DataResponse<Http::STATUS_OK, LibresignConfigureChecksResponse, array{}>
	 *
	 * 200: OK
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/configure-check', requirements: ['apiVersion' => '(v1)'])]
	public function configureCheck(): DataResponse {
		/** @var LibresignConfigureChecksResponse $configureCheckList */
		$configureCheckList = array_values($this->configureCheckService->checkAll());
		return new DataResponse(
			$configureCheckList
		);
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
			if ($this->appConfig->getValueString(Application::APP_ID, 'certificate_engine') === 'cfssl') {
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

	/**
	 * Add custom background image
	 *
	 * @return DataResponse<Http::STATUS_OK, LibresignSuccessStatusResponse, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, LibresignFailureStatusResponse, array{}>
	 *
	 * 200: OK
	 * 422: Error
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/signature-background', requirements: ['apiVersion' => '(v1)'])]
	public function signatureBackgroundSave(): DataResponse {
		$image = $this->request->getUploadedFile('image');
		$uploadError = $this->validateUploadedFile($image, 'image');
		if ($uploadError !== null) {
			return $uploadError;
		}
		try {
			$this->signatureBackgroundService->updateImage($image['tmp_name']);
		} catch (\Exception $e) {
			return new DataResponse(
				[
					'message' => $e->getMessage(),
					'status' => 'failure',
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		return new DataResponse(
			[
				'status' => 'success',
			]
		);
	}

	/**
	 * Get custom background image
	 *
	 * @return FileDisplayResponse<Http::STATUS_OK, array{}>
	 *
	 * 200: Image returned
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/signature-background', requirements: ['apiVersion' => '(v1)'])]
	public function signatureBackgroundGet(): FileDisplayResponse {
		$file = $this->signatureBackgroundService->getImage();

		$response = new FileDisplayResponse($file);
		$csp = new ContentSecurityPolicy();
		$csp->allowInlineStyle();
		$response->setContentSecurityPolicy($csp);
		$response->cacheFor(3600);
		$response->addHeader('Content-Type', 'image/png');
		$response->addHeader('Content-Disposition', 'attachment; filename="background.png"');
		$response->addHeader('Content-Type', 'image/png');
		return $response;
	}

	/**
	 * Delete background image
	 *
	 * @return DataResponse<Http::STATUS_OK, LibresignSuccessStatusResponse, array{}>
	 *
	 * 200: Deleted with success
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/admin/signature-background', requirements: ['apiVersion' => '(v1)'])]
	public function signatureBackgroundDelete(): DataResponse {
		$this->signatureBackgroundService->delete();
		return new DataResponse(
			[
				'status' => 'success',
			]
		);
	}

	/**
	 * Upload new certificate policy PDF for this instance
	 *
	 * @return DataResponse<Http::STATUS_OK, LibresignCertificatePolicyResponse, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, LibresignFailureStatusResponse, array{}>
	 *
	 * 200: OK
	 * 422: Upload or validation error
	 */
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/certificate-policy', requirements: ['apiVersion' => '(v1)'])]
	public function saveCertificatePolicy(): DataResponse {
		// Handle POST method - upload PDF
		$pdf = $this->request->getUploadedFile('pdf');
		$uploadError = $this->validateUploadedFile($pdf, 'pdf');
		if ($uploadError !== null) {
			return $uploadError;
		}
		try {
			$cps = $this->certificatePolicyService->updateFile($pdf['tmp_name']);
		} catch (UnexpectedValueException $e) {
			return new DataResponse(
				[
					'message' => $e->getMessage(),
					'status' => 'failure',
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		return new DataResponse(
			[
				'CPS' => $cps,
				'status' => 'success',
			]
		);
	}

	/**
	 * Delete certificate policy of this instance
	 *
	 * @return DataResponse<Http::STATUS_OK, array{status: 'success'}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, LibresignFailureStatusResponse, array{}>
	 *
	 * 200: OK
	 * 422: Error
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/admin/certificate-policy', requirements: ['apiVersion' => '(v1)'])]
	public function deleteCertificatePolicy(): DataResponse {
		try {
			$this->certificatePolicyService->deleteFile();
		} catch (\Exception $e) {
			return new DataResponse(
				[
					'message' => $e->getMessage(),
					'status' => 'failure',
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		return new DataResponse(['status' => 'success']);
	}

	/**
	 * Update OID
	 *
	 * @param string $oid OID is a unique numeric identifier for certificate policies in digital certificates.
	 * @return DataResponse<Http::STATUS_OK, LibresignSuccessStatusResponse, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, LibresignFailureStatusResponse, array{}>
	 *
	 * 200: OK
	 * 422: Validation error
	 */
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/certificate-policy/oid', requirements: ['apiVersion' => '(v1)'])]
	public function updateOid(string $oid): DataResponse {
		try {
			$this->certificatePolicyService->updateOid($oid);
			return new DataResponse(
				[
					'status' => 'success',
				]
			);
		} catch (\Exception $e) {
			return new DataResponse(
				[
					'message' => $e->getMessage(),
					'status' => 'failure',
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
	}

	/**
	 * Get list of files currently being signed (status = SIGNING_IN_PROGRESS)
	 *
	 * @return DataResponse<Http::STATUS_OK, LibresignActiveSigningsResponse, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: List of active signings
	 */
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/active-signings', requirements: ['apiVersion' => '(v1)'])]
	public function getActiveSignings(): DataResponse {
		try {
			$activeSignings = $this->fileMapper->findByStatus(FileStatus::SIGNING_IN_PROGRESS->value);

			$result = [];
			foreach ($activeSignings as $file) {
				$result[] = [
					'id' => $file->getId(),
					'uuid' => $file->getUuid(),
					'name' => $file->getName(),
					'signerEmail' => $file->getSignerEmail() ?? '',
					'signerDisplayName' => $file->getSignerName() ?? '',
					'updatedAt' => $file->getUpdatedAt(),
				];
			}

			return new DataResponse([
				'data' => $result,
			]);
		} catch (\Exception $e) {
			return new DataResponse([
				'error' => $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
