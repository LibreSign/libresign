<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\Install\ConfigureCheckService;
use OCA\Libresign\Service\Install\InstallService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IEventSource;
use OCP\IEventSourceFactory;
use OCP\IL10N;
use OCP\IRequest;

/**
 * @psalm-import-type LibresignRootCertificate from ResponseDefinitions
 */
class AdminController extends AEnvironmentAwareController {
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

	/**
	 * Generate certificate using CFSSL engine
	 *
	 * @param array{commonName: string, names: array<string, array{value:string}>} $rootCert fields of root certificate
	 * @param string $cfsslUri URI of CFSSL API
	 * @param string $configPath Path of config files of CFSSL
	 * @return DataResponse<Http::STATUS_OK, array{configPath: string, rootCert: LibresignRootCertificate}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 401: Account not found
	 */
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

	/**
	 * Generate certificate using OpenSSL engine
	 *
	 * @param array{commonName: string, names: array<string, array{value:string}>} $rootCert fields of root certificate
	 * @param string $configPath Path of config files of CFSSL
	 * @return DataResponse<Http::STATUS_OK, array{configPath: string, rootCert: LibresignRootCertificate}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 401: Account not found
	 */
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

	/**
	 * Load certificate data
	 *
	 * Return all data of root certificate and a field called `generated` with a boolean value.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{configPath: string, rootCert: LibresignRootCertificate, generated: boolean}, array{}>
	 *
	 * 200: OK
	 */
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

	/**
	 * Check the configuration of LibreSign
	 *
	 * Return the status of necessary configuration and tips to fix the problems.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>
	 *
	 * 200: OK
	 */
	#[NoCSRFRequired]
	public function configureCheck(): DataResponse {
		return new DataResponse(
			$this->configureCheckService->checkAll()
		);
	}

	/**
	 * @IgnoreOpenAPI
	 */
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
