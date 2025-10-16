<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use DateTimeInterface;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\Certificate\ValidateService;
use OCA\Libresign\Service\CertificatePolicyService;
use OCA\Libresign\Service\Install\ConfigureCheckService;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\ReminderService;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Settings\Admin;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Files\SimpleFS\InMemoryFile;
use OCP\IAppConfig;
use OCP\IEventSource;
use OCP\IEventSourceFactory;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use UnexpectedValueException;

/**
 * @psalm-import-type LibresignEngineHandler from ResponseDefinitions
 * @psalm-import-type LibresignCetificateDataGenerated from ResponseDefinitions
 * @psalm-import-type LibresignConfigureCheck from ResponseDefinitions
 * @psalm-import-type LibresignRootCertificate from ResponseDefinitions
 * @psalm-import-type LibresignReminderSettings from ResponseDefinitions
 */
class AdminController extends AEnvironmentAwareController {
	private IEventSource $eventSource;
	public function __construct(
		IRequest $request,
		private IAppConfig $appConfig,
		private ConfigureCheckService $configureCheckService,
		private InstallService $installService,
		private CertificateEngineFactory $certificateEngineFactory,
		private IEventSourceFactory $eventSourceFactory,
		private SignatureTextService $signatureTextService,
		private IL10N $l10n,
		protected ISession $session,
		private SignatureBackgroundService $signatureBackgroundService,
		private CertificatePolicyService $certificatePolicyService,
		private ValidateService $validateService,
		private ReminderService $reminderService,
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
	): IEngineHandler {
		$names = [];
		if (isset($rootCert['names'])) {
			$this->validateService->validateNames($rootCert['names']);
			foreach ($rootCert['names'] as $item) {
				$names[$item['id']]['value'] = trim((string)$item['value']);
			}
		}
		$this->validateService->validate('CN', $rootCert['commonName']);
		$this->installService->generate(
			trim((string)$rootCert['commonName']),
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
	 * @return DataResponse<Http::STATUS_OK, LibresignCetificateDataGenerated, array{}>
	 *
	 * 200: OK
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/certificate', requirements: ['apiVersion' => '(v1)'])]
	public function loadCertificate(): DataResponse {
		$engine = $this->certificateEngineFactory->getEngine();
		/** @var LibresignEngineHandler */
		$certificate = $engine->toArray();
		$configureResult = $engine->configureCheck();
		$success = array_filter(
			$configureResult,
			fn (ConfigureCheckHelper $config) => $config->getStatus() === 'success'
		);
		$certificate['generated'] = count($success) === count($configureResult);

		return new DataResponse($certificate);
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
	 * @return DataResponse<Http::STATUS_OK, array{status: 'success'}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{status: 'failure', message: string}, array{}>
	 *
	 * 200: OK
	 * 422: Error
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/signature-background', requirements: ['apiVersion' => '(v1)'])]
	public function signatureBackgroundSave(): DataResponse {
		$image = $this->request->getUploadedFile('image');
		$phpFileUploadErrors = [
			UPLOAD_ERR_OK => $this->l10n->t('The file was uploaded'),
			UPLOAD_ERR_INI_SIZE => $this->l10n->t('The uploaded file exceeds the upload_max_filesize directive in php.ini'),
			UPLOAD_ERR_FORM_SIZE => $this->l10n->t('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
			UPLOAD_ERR_PARTIAL => $this->l10n->t('The file was only partially uploaded'),
			UPLOAD_ERR_NO_FILE => $this->l10n->t('No file was uploaded'),
			UPLOAD_ERR_NO_TMP_DIR => $this->l10n->t('Missing a temporary folder'),
			UPLOAD_ERR_CANT_WRITE => $this->l10n->t('Could not write file to disk'),
			UPLOAD_ERR_EXTENSION => $this->l10n->t('A PHP extension stopped the file upload'),
		];
		if (empty($image)) {
			$error = $this->l10n->t('No file uploaded');
		} elseif (!empty($image) && array_key_exists('error', $image) && $image['error'] !== UPLOAD_ERR_OK) {
			$error = $phpFileUploadErrors[$image['error']];
		}
		if ($error !== null) {
			return new DataResponse(
				[
					'message' => $error,
					'status' => 'failure',
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
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
	 * Reset the background image to be the default of LibreSign
	 *
	 * @return DataResponse<Http::STATUS_OK, array{status: 'success'}, array{}>
	 *
	 * 200: Image reseted to default
	 */
	#[ApiRoute(verb: 'PATCH', url: '/api/{apiVersion}/admin/signature-background', requirements: ['apiVersion' => '(v1)'])]
	public function signatureBackgroundReset(): DataResponse {
		$this->signatureBackgroundService->reset();
		return new DataResponse(
			[
				'status' => 'success',
			]
		);
	}

	/**
	 * Delete background image
	 *
	 * @return DataResponse<Http::STATUS_OK, array{status: 'success'}, array{}>
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
	 * Save signature text service
	 *
	 * @param string $template Template to signature text
	 * @param float $templateFontSize Font size used when print the parsed text of this template at PDF file
	 * @param float $signatureFontSize Font size used when the signature mode is SIGNAME_AND_DESCRIPTION
	 * @param float $signatureWidth Signature width
	 * @param float $signatureHeight Signature height
	 * @param string $renderMode Signature render mode
	 * @return DataResponse<Http::STATUS_OK, array{template: string, parsed: string, templateFontSize: float, signatureFontSize: float, signatureWidth: float, signatureHeight: float, renderMode: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: OK
	 * 400: Bad request
	 */
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/signature-text', requirements: ['apiVersion' => '(v1)'])]
	public function signatureTextSave(
		string $template,
		/** @todo openapi package don't evaluate SignatureTextService::TEMPLATE_DEFAULT_FONT_SIZE */
		float $templateFontSize = 10,
		/** @todo openapi package don't evaluate SignatureTextService::SIGNATURE_DEFAULT_FONT_SIZE */
		float $signatureFontSize = 20,
		/** @todo openapi package don't evaluate SignatureTextService::DEFAULT_SIGNATURE_WIDTH */
		float $signatureWidth = 350,
		/** @todo openapi package don't evaluate SignatureTextService::DEFAULT_SIGNATURE_HEIGHT */
		float $signatureHeight = 100,
		string $renderMode = 'GRAPHIC_AND_DESCRIPTION',
	): DataResponse {
		try {
			$return = $this->signatureTextService->save(
				$template,
				$templateFontSize,
				$signatureFontSize,
				$signatureWidth,
				$signatureHeight,
				$renderMode,
			);
			return new DataResponse(
				$return,
				Http::STATUS_OK
			);
		} catch (LibresignException $th) {
			return new DataResponse(
				[
					'error' => $th->getMessage(),
				],
				Http::STATUS_BAD_REQUEST
			);
		}
	}

	/**
	 * Get parsed signature text service
	 *
	 * @param string $template Template to signature text
	 * @param string $context Context for parsing the template
	 * @return DataResponse<Http::STATUS_OK, array{template: string,parsed: string, templateFontSize: float, signatureFontSize: float, signatureWidth: float, signatureHeight: float, renderMode: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: OK
	 * 400: Bad request
	 */
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/signature-text', requirements: ['apiVersion' => '(v1)'])]
	public function signatureTextGet(string $template = '', string $context = ''): DataResponse {
		$context = json_decode($context, true) ?? [];
		try {
			$return = $this->signatureTextService->parse($template, $context);
			return new DataResponse(
				$return,
				Http::STATUS_OK
			);
		} catch (LibresignException $th) {
			return new DataResponse(
				[
					'error' => $th->getMessage(),
				],
				Http::STATUS_BAD_REQUEST
			);
		}
	}

	/**
	 * Get signature settings
	 *
	 * @return DataResponse<Http::STATUS_OK, array{default_signature_text_template: string, signature_available_variables: array<string, string>}, array{}>
	 *
	 * 200: OK
	 */
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/signature-settings', requirements: ['apiVersion' => '(v1)'])]
	public function getSignatureSettings(): DataResponse {
		$response = [
			'signature_available_variables' => $this->signatureTextService->getAvailableVariables(),
			'default_signature_text_template' => $this->signatureTextService->getDefaultTemplate(),
		];
		return new DataResponse($response);
	}

	/**
	 * Convert signer name as image
	 *
	 * @param int $width Image width,
	 * @param int $height Image height
	 * @param string $text Text to be added to image
	 * @param float $fontSize Font size of text
	 * @param bool $isDarkTheme Color of text, white if is tark theme and black if not
	 * @param string $align Align of text: left, center or right
	 * @return FileDisplayResponse<Http::STATUS_OK, array{Content-Disposition: 'inline; filename="signer-name.png"', Content-Type: 'image/png'}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: OK
	 * 400: Bad request
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/signer-name', requirements: ['apiVersion' => '(v1)'])]
	public function signerName(
		int $width,
		int $height,
		string $text,
		float $fontSize,
		bool $isDarkTheme,
		string $align,
	):  FileDisplayResponse|DataResponse {
		try {
			$blob = $this->signatureTextService->signerNameImage(
				width: $width,
				height: $height,
				text: $text,
				fontSize: $fontSize,
				isDarkTheme: $isDarkTheme,
				align: $align,
			);
			$file = new InMemoryFile('signer-name.png', $blob);
			return new FileDisplayResponse($file, Http::STATUS_OK, [
				'Content-Disposition' => 'inline; filename="signer-name.png"',
				'Content-Type' => 'image/png',
			]);
		} catch (LibresignException $th) {
			return new DataResponse(
				[
					'error' => $th->getMessage(),
				],
				Http::STATUS_BAD_REQUEST
			);
		}
	}

	/**
	 * Update certificate policy of this instance
	 *
	 * @return DataResponse<Http::STATUS_OK, array{status: 'success', CPS: string}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{status: 'failure', message: string}, array{}>
	 *
	 * 200: OK
	 * 422: Not found
	 */
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/certificate-policy', requirements: ['apiVersion' => '(v1)'])]
	public function saveCertificatePolicy(): DataResponse {
		$pdf = $this->request->getUploadedFile('pdf');
		$phpFileUploadErrors = [
			UPLOAD_ERR_OK => $this->l10n->t('The file was uploaded'),
			UPLOAD_ERR_INI_SIZE => $this->l10n->t('The uploaded file exceeds the upload_max_filesize directive in php.ini'),
			UPLOAD_ERR_FORM_SIZE => $this->l10n->t('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
			UPLOAD_ERR_PARTIAL => $this->l10n->t('The file was only partially uploaded'),
			UPLOAD_ERR_NO_FILE => $this->l10n->t('No file was uploaded'),
			UPLOAD_ERR_NO_TMP_DIR => $this->l10n->t('Missing a temporary folder'),
			UPLOAD_ERR_CANT_WRITE => $this->l10n->t('Could not write file to disk'),
			UPLOAD_ERR_EXTENSION => $this->l10n->t('A PHP extension stopped the file upload'),
		];
		if (empty($pdf)) {
			$error = $this->l10n->t('No file uploaded');
		} elseif (!empty($pdf) && array_key_exists('error', $pdf) && $pdf['error'] !== UPLOAD_ERR_OK) {
			$error = $phpFileUploadErrors[$pdf['error']];
		}
		if ($error !== null) {
			return new DataResponse(
				[
					'message' => $error,
					'status' => 'failure',
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
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
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>
	 *
	 * 200: OK
	 * 404: Not found
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/admin/certificate-policy', requirements: ['apiVersion' => '(v1)'])]
	public function deleteCertificatePolicy(): DataResponse {
		$this->certificatePolicyService->deleteFile();
		return new DataResponse();
	}

	/**
	 * Update OID
	 *
	 * @param string $oid OID is a unique numeric identifier for certificate policies in digital certificates.
	 * @return DataResponse<Http::STATUS_OK, array{status: 'success'}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{status: 'failure', message: string}, array{}>
	 *
	 * 200: OK
	 * 422: Validation error
	 */
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/certificate-policy/oid', requirements: ['apiVersion' => '(v1)'])]
	public function updateOID(string $oid): DataResponse {
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
	 * Get reminder settings
	 *
	 * @return DataResponse<Http::STATUS_OK, LibresignReminderSettings, array{}>
	 *
	 * 200: OK
	 */
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/reminder', requirements: ['apiVersion' => '(v1)'])]
	public function reminderFetch(): DataResponse {
		$response = $this->reminderService->getSettings();
		if ($response['next_run'] instanceof \DateTime) {
			$response['next_run'] = $response['next_run']->format(DateTimeInterface::ATOM);
		}
		return new DataResponse($response);
	}

	/**
	 * Save reminder
	 *
	 * @param int $daysBefore First reminder after (days)
	 * @param int $daysBetween Days between reminders
	 * @param int $max Max reminders per signer
	 * @param string $sendTimer Send time (HH:mm)
	 * @return DataResponse<Http::STATUS_OK, LibresignReminderSettings, array{}>
	 *
	 * 200: OK
	 */
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/reminder', requirements: ['apiVersion' => '(v1)'])]
	public function reminderSave(
		int $daysBefore,
		int $daysBetween,
		int $max,
		string $sendTimer,
	): DataResponse {
		$response = $this->reminderService->save($daysBefore, $daysBetween, $max, $sendTimer);
		if ($response['next_run'] instanceof \DateTime) {
			$response['next_run'] = $response['next_run']->format(DateTimeInterface::ATOM);
		}
		return new DataResponse($response);
	}

	/**
	 * Set TSA configuration values with proper sensitive data handling
	 *
	 * Only saves configuration if tsa_url is provided. Automatically manages
	 * username/password fields based on authentication type.
	 *
	 * @param string|null $tsa_url TSA server URL (required for saving)
	 * @param string|null $tsa_policy_oid TSA policy OID
	 * @param string|null $tsa_auth_type Authentication type (none|basic), defaults to 'none'
	 * @param string|null $tsa_username Username for basic authentication
	 * @param string|null $tsa_password Password for basic authentication (stored as sensitive data)
	 * @return DataResponse<Http::STATUS_OK, array{status: 'success'}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{status: 'error', message: string}, array{}>
	 *
	 * 200: OK
	 * 400: Validation error
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/tsa', requirements: ['apiVersion' => '(v1)'])]
	public function setTsaConfig(
		?string $tsa_url = null,
		?string $tsa_policy_oid = null,
		?string $tsa_auth_type = null,
		?string $tsa_username = null,
		?string $tsa_password = null,
	): DataResponse {
		if (empty($tsa_url)) {
			return $this->deleteTsaConfig();
		}

		$trimmedUrl = trim($tsa_url);
		if (!filter_var($trimmedUrl, FILTER_VALIDATE_URL)
			|| !in_array(parse_url($trimmedUrl, PHP_URL_SCHEME), ['http', 'https'])) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'Invalid URL format'
			], Http::STATUS_BAD_REQUEST);
		}

		$this->appConfig->setValueString(Application::APP_ID, 'tsa_url', $trimmedUrl);

		if (empty($tsa_policy_oid)) {
			$this->appConfig->deleteKey(Application::APP_ID, 'tsa_policy_oid');
		} else {
			$trimmedOid = trim($tsa_policy_oid);
			if (!preg_match('/^[0-9]+(\.[0-9]+)*$/', $trimmedOid)) {
				return new DataResponse([
					'status' => 'error',
					'message' => 'Invalid OID format'
				], Http::STATUS_BAD_REQUEST);
			}
			$this->appConfig->setValueString(Application::APP_ID, 'tsa_policy_oid', $trimmedOid);
		}

		$authType = $tsa_auth_type ?? 'none';
		$this->appConfig->setValueString(Application::APP_ID, 'tsa_auth_type', $authType);

		if ($authType === 'basic') {
			$hasUsername = !empty($tsa_username);
			$hasPassword = !empty($tsa_password) && $tsa_password !== Admin::PASSWORD_PLACEHOLDER;

			if (!$hasUsername && !$hasPassword) {
				return new DataResponse([
					'status' => 'error',
					'message' => 'Username and password are required for basic authentication'
				], Http::STATUS_BAD_REQUEST);
			} elseif (!$hasUsername) {
				return new DataResponse([
					'status' => 'error',
					'message' => 'Username is required'
				], Http::STATUS_BAD_REQUEST);
			} elseif (!$hasPassword) {
				return new DataResponse([
					'status' => 'error',
					'message' => 'Password is required'
				], Http::STATUS_BAD_REQUEST);
			}

			$this->appConfig->setValueString(Application::APP_ID, 'tsa_username', trim($tsa_username));
			$this->appConfig->setValueString(
				Application::APP_ID,
				key: 'tsa_password',
				value: $tsa_password,
				sensitive: true,
			);
		} else {
			$this->appConfig->deleteKey(Application::APP_ID, 'tsa_username');
			$this->appConfig->deleteKey(Application::APP_ID, 'tsa_password');
		}

		return new DataResponse(['status' => 'success']);
	}

	/**
	 * Delete TSA configuration
	 *
	 * Delete all TSA configuration fields from the application settings.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{status: 'success'}, array{}>
	 *
	 * 200: OK
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/admin/tsa', requirements: ['apiVersion' => '(v1)'])]
	public function deleteTsaConfig(): DataResponse {
		$fields = ['tsa_url', 'tsa_policy_oid', 'tsa_auth_type', 'tsa_username', 'tsa_password'];

		foreach ($fields as $field) {
			$this->appConfig->deleteKey(Application::APP_ID, $field);
		}

		return new DataResponse(['status' => 'success']);
	}
}
