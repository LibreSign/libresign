<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Service\CaIdentifierService;
use OCA\Libresign\Service\Install\InstallService;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version13000Date20251031165700 extends SimpleMigrationStep {
	public function __construct(
		private IConfig $config,
		private IAppConfig $appConfig,
		private CertificateEngineFactory $certificateEngineFactory,
		private CaIdentifierService $caIdentifierService,
		private InstallService $installService,
	) {
	}

	/**
	 * Fix config path for OpenSSL engine when this info does not exist
	 *
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$engineName = $this->appConfig->getValueString(Application::APP_ID, 'certificate_engine', '');
		if ($engineName === 'openssl') {
			$engine = $this->certificateEngineFactory->getEngine();
			$configPath = $this->appConfig->getValueString(Application::APP_ID, 'config_path', '');
			if ($configPath === '') {
				$engine->setConfigPath($engine->getConfigPath());
			}
		}

		$this->convertRootCertOuStringToArray();
		$this->migrateToNewestConfigFormat();

		if ($schema->hasTable('libresign_crl')) {
			$crlTable = $schema->getTable('libresign_crl');
			if (!$crlTable->hasColumn('engine')) {
				$crlTable->addColumn('engine', 'string', ['default' => $engineName]);
			}
		}
		return $schema;
	}

	private function migrateToNewestConfigFormat(): void {
		$dataDir = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data/');
		$rootPath = $dataDir . '/appdata_' . $this->config->getSystemValue('instanceid') . '/libresign/';
		if (!is_dir($rootPath)) {
			return;
		}

		$instanceId = $this->installService->getInstanceId();
		$originalCaId = $this->appConfig->getValueString(Application::APP_ID, 'ca_id');
		if (empty($originalCaId)) {
			$engineName = $this->appConfig->getValueString(Application::APP_ID, 'certificate_engine');
			if ($engineName) {
				$originalCaId = $this->caIdentifierService->generateCaId($instanceId, $engineName);
			}
		}
		$generatedNewCaId = false;

		$engines = ['o' => 'openssl', 'c' => 'cfssl'];
		foreach ($engines as $engineType => $engineName) {
			if (!is_dir($rootPath . $engineName . '_config')) {
				continue;
			}

			$engine = $this->certificateEngineFactory->getEngine($engineName);

			if (empty($originalCaId) || !str_ends_with($originalCaId, '-e:' . $engineType)) {
				$generatedNewCaId = true;
				$this->caIdentifierService->generateCaId($instanceId, $engineName);
			}

			$this->appConfig->deleteKey(Application::APP_ID, 'config_path');
			$configPath = $engine->getConfigPath();
			$configFiles = glob($rootPath . $engineName . '_config/*');

			if (!empty($configFiles) && empty(glob($configPath . '/*'))) {
				foreach ($configFiles as $file) {
					if (is_file($file)) {
						copy($file, $configPath . '/' . basename($file));
					}
				}
			}

			if (!empty($configFiles)) {
				foreach ($configFiles as $file) {
					if (is_file($file)) {
						unlink($file);
					}
				}
			}
			if (is_dir($rootPath . $engineName . '_config')) {
				rmdir($rootPath . $engineName . '_config');
			}
		}

		if ($generatedNewCaId && $originalCaId) {
			$this->appConfig->setValueString(Application::APP_ID, 'ca_id', $originalCaId);
		}
	}

	private function convertRootCertOuStringToArray(): void {
		$rootCert = $this->appConfig->getValueArray(Application::APP_ID, 'rootCert');
		if (!$rootCert || !isset($rootCert['names']['OU']['value'])) {
			return;
		}

		$ouValue = $rootCert['names']['OU']['value'];

		if (is_string($ouValue)) {
			$rootCert['names']['OU']['value'] = [$ouValue];
			$this->appConfig->setValueArray(Application::APP_ID, 'rootCert', $rootCert);
		}
	}
}
