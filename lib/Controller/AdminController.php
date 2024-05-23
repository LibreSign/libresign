<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCA\Libresign\Service\Install\ConfigureCheckService;
use OCA\Libresign\Service\Install\InstallService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IEventSource;
use OCP\IEventSourceFactory;
use OCP\IL10N;
use OCP\IRequest;

class AdminController extends Controller {
	private IEventSource $eventSource;
	public function __construct(
		IRequest $request,
		private ConfigureCheckService $configureCheckService,
		private InstallService $installService,
		private CertificateEngineHandler $certificateEngineHandler,
		private IEventSourceFactory $eventSourceFactory,
		private IL10N $l10n,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->eventSource = $this->eventSourceFactory->create();
	}

	#[NoCSRFRequired]
	public function generateCertificateCfssl(
		array $rootCert,
		string $cfsslUri = '',
		string $configPath = ''
	): JSONResponse {
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
	): JSONResponse {
		return $this->generateCertificate($rootCert, [
			'engine' => 'openssl',
			'configPath' => trim($configPath),
		]);
	}

	private function generateCertificate(
		array $rootCert,
		array $properties = [],
	): JSONResponse {
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

			return new JSONResponse([
				'data' => $this->certificateEngineHandler->getEngine()->toArray(),
			]);
		} catch (\Exception $exception) {
			return new JSONResponse(
				[
					'message' => $exception->getMessage()
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	#[NoCSRFRequired]
	public function loadCertificate(): JSONResponse {
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

		return new JSONResponse($certificate);
	}

	private function trimAndThrowIfEmpty(string $key, $value): string {
		if (empty($value)) {
			throw new LibresignException("parameter '{$key}' is required!", 400);
		}
		return trim($value);
	}

	#[NoCSRFRequired]
	public function configureCheck(): JSONResponse {
		return new JSONResponse(
			$this->configureCheckService->checkAll()
		);
	}

	#[NoCSRFRequired]
	public function installAndValidate(): void {
		try {
			$async = \function_exists('proc_open');
			$this->installService->installJava($async);
			$this->installService->installJSignPdf($async);
			$this->installService->installPdftk($async);
			$this->installService->installCfssl($async);

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
				if ($seconds === 5) {
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
