<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version11000Date20250103005204 extends SimpleMigrationStep {
	public function __construct(
		private IAppConfig $appConfig,
		private IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper */
		$schema = $schemaClosure();

		$tableFile = $schema->getTable('libresign_file');
		if (!$tableFile->hasColumn('signed_hash')) {
			$tableFile->addColumn('signed_hash', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
		}

		$tableSignRequest = $schema->getTable('libresign_sign_request');
		if (!$tableSignRequest->hasColumn('signed_hash')) {
			$tableSignRequest->addColumn('signed_hash', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
		}

		$qb = $this->connection->getQueryBuilder();
		$items = $this->appConfig->getAllValues(Application::APP_ID);
		$qb->update('appconfig')
			->set('type', $qb->createParameter('type'))
			->where($qb->expr()->eq('appid', $qb->createParameter('appid')))
			->andWhere($qb->expr()->eq('configkey', $qb->createParameter('configkey')));
		foreach ($items as $key => $value) {
			if (in_array($key, ['approval_group', 'groups_request_sign', 'identify_methods', 'root_cert', 'rootCert']) && !is_array($value)) {
				$qb->setParameter('type', IAppConfig::VALUE_ARRAY);
				$qb->setParameter('appid', Application::APP_ID);
				$qb->setParameter('configkey', $key);
				$qb->executeStatement();
			} elseif (in_array($key, ['add_footer', 'collect_metadata', 'identification_documents', 'make_validation_url_private', 'notify_unsigned_user', 'write_qrcode_on_footer']) && !is_bool($value)) {
				$qb->setParameter('type', IAppConfig::VALUE_BOOL);
				$qb->setParameter('appid', Application::APP_ID);
				$qb->setParameter('configkey', $key);
				$qb->executeStatement();
			} elseif (in_array($key, ['expiry_in_days', 'length_of_page', 'maximum_validity']) && !is_int($value)) {
				$qb->setParameter('type', IAppConfig::VALUE_INT);
				$qb->setParameter('appid', Application::APP_ID);
				$qb->setParameter('configkey', $key);
				$qb->executeStatement();
			}
		}
		if (array_key_exists('root_cert', $items)) {
			if (!array_key_exists('rootCert', $items)) {
				$value = $this->appConfig->getValueArray(Application::APP_ID, 'root_cert');
				$this->appConfig->setValueArray(Application::APP_ID, 'rootCert', $value);
			}
			$this->appConfig->deleteKey(Application::APP_ID, 'root_cert');
		}

		return $schema;
	}
}
