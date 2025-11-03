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
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version13000Date20251031165700 extends SimpleMigrationStep {
	public function __construct(
		private IAppConfig $appConfig,
		private CertificateEngineFactory $certificateEngineFactory,
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
		$engineName = $this->appConfig->getValueString(Application::APP_ID, 'certificate_engine', 'openssl');
		if ($engineName === 'openssl') {
			$engine = $this->certificateEngineFactory->getEngine();
			$configPath = $this->appConfig->getValueString(Application::APP_ID, 'config_path', '');
			if ($configPath === '') {
				$engine->setConfigPath($engine->getConfigPath());
			}
		}

		$this->convertRootCertOuStringToArray();

		if ($schema->hasTable('libresign_crl')) {
			$crlTable = $schema->getTable('libresign_crl');
			if (!$crlTable->hasColumn('engine')) {
				$crlTable->addColumn('engine', 'string', ['default' => $engineName]);
			}
		}
		return $schema;
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
