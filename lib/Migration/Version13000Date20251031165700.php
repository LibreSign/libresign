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
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDBConnection;
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
		private IDBConnection $connection,
	) {
	}

	/**
	 * Prepare operations before schema changes
	 *
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->addConfigPathToOpenSsl();
		$this->convertRootCertOuStringToArray();
	}

	/**
	 * Apply schema changes to the database
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
		if ($schema->hasTable('libresign_crl')) {
			$crlTable = $schema->getTable('libresign_crl');
			if (!$crlTable->hasColumn('engine')) {
				$crlTable->addColumn('engine', 'string', ['default' => $engineName]);
			}
			if (!$crlTable->hasColumn('instance_id')) {
				$crlTable->addColumn('instance_id', 'string', ['notnull' => false]);
			}
			if (!$crlTable->hasColumn('generation')) {
				$crlTable->addColumn('generation', 'integer', ['notnull' => false]);
			}
		}
		return $schema;
	}

	/**
	 * Execute operations that depend on the new schema
	 *
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->migrateToNewestConfigFormat();
		$this->populateCrlInstanceAndGeneration();
	}

	private function addConfigPathToOpenSsl(): void {
		$engineName = $this->appConfig->getValueString(Application::APP_ID, 'certificate_engine', '');
		if ($engineName !== 'openssl') {
			return;
		}
		$engine = $this->certificateEngineFactory->getEngine();
		$configPath = $this->appConfig->getValueString(Application::APP_ID, 'config_path', '');
		if (empty($configPath)) {
			$engine->setConfigPath($engine->getCurrentConfigPath());
		}
	}

	private function migrateToNewestConfigFormat(): void {
		$dataDir = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data/');
		$rootPath = $dataDir . '/appdata_' . $this->config->getSystemValue('instanceid') . '/libresign/';
		if (!is_dir($rootPath)) {
			return;
		}

		$originalCaId = $this->appConfig->getValueString(Application::APP_ID, 'ca_id');
		if (empty($originalCaId)) {
			$engineName = $this->appConfig->getValueString(Application::APP_ID, 'certificate_engine');
			if ($engineName) {
				$originalCaId = $this->caIdentifierService->generateCaId($engineName);
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
				$this->caIdentifierService->generateCaId($engineName);
			}

			$this->appConfig->deleteKey(Application::APP_ID, 'config_path');
			$configPath = $engine->getCurrentConfigPath();
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

	private function populateCrlInstanceAndGeneration(): void {
		$currentCaId = $this->appConfig->getValueString(Application::APP_ID, 'ca_id');
		if (empty($currentCaId)) {
			return;
		}

		try {
			$pattern = '/^libresign-ca-id:(?P<instanceId>[a-z0-9]+)_g:(?P<generation>\d+)_e:(?P<engineType>[oc])$/';
			if (!preg_match($pattern, $currentCaId, $matches)) {
				return;
			}

			$instanceId = $matches['instanceId'];
			$generation = (int)$matches['generation'];
			$engineType = $matches['engineType'];
			$engineName = $engineType === 'o' ? 'openssl' : 'cfssl';

			$rootCertCreationDate = $this->getRootCertificateCreationDate();
			if ($rootCertCreationDate === null) {
				return;
			}

			$qb = $this->connection->getQueryBuilder();
			$qb->update('libresign_crl')
				->set('instance_id', $qb->createNamedParameter($instanceId))
				->set('generation', $qb->createNamedParameter($generation, IQueryBuilder::PARAM_INT))
				->set('engine', $qb->createNamedParameter($engineName))
				->where($qb->expr()->gte('issued_at', $qb->createNamedParameter($rootCertCreationDate->getTimestamp(), IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->isNull('instance_id'));

			$qb->executeStatement();

		} catch (\Exception $e) {
			return;
		}
	}

	private function getRootCertificateCreationDate(): ?\DateTime {
		try {
			$currentCaId = $this->appConfig->getValueString(Application::APP_ID, 'ca_id');
			if (empty($currentCaId)) {
				return null;
			}

			$pattern = '/^libresign-ca-id:(?P<instanceId>[a-z0-9]+)_g:(?P<generation>\d+)_e:(?P<engineType>[oc])$/';
			if (!preg_match($pattern, $currentCaId, $matches)) {
				return null;
			}

			$instanceId = $matches['instanceId'];
			$generation = (int)$matches['generation'];
			$engineType = $matches['engineType'];
			$engineName = $engineType === 'o' ? 'openssl' : 'cfssl';

			$engine = $this->certificateEngineFactory->getEngine($engineName);
			$configPath = $engine->getConfigPathByParams($instanceId, $generation);
			$caCertPath = $configPath . DIRECTORY_SEPARATOR . 'ca.pem';

			if (!file_exists($caCertPath)) {
				return null;
			}

			$certContent = file_get_contents($caCertPath);
			if (!$certContent) {
				return null;
			}

			$x509Resource = openssl_x509_read($certContent);
			if (!$x509Resource) {
				return null;
			}

			$parsed = openssl_x509_parse($x509Resource);
			if (!$parsed || !isset($parsed['validFrom_time_t'])) {
				return null;
			}

			return new \DateTime('@' . $parsed['validFrom_time_t']);

		} catch (\Exception $e) {
			return null;
		}
	}
}
