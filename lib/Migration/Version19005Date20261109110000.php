<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Remove legacy callback destinations after preserving any configured values.
 */
final class Version19005Date20261109110000 extends SimpleMigrationStep {
	private const FILE_BACKUP = 'backup-table-libresign_file_callback_Version19005Date20261109110000.csv';
	private const CONFIG_BACKUP = 'backup-config-webhook_sign_url_Version19005Date20261109110000.json';

	private IAppData $appData;

	public function __construct(
		private IAppConfig $appConfig,
		private IDBConnection $connection,
		IAppDataFactory $appDataFactory,
	) {
		$this->appData = $appDataFactory->get(Application::APP_ID);
	}

	/** @param Closure(): ISchemaWrapper $schemaClosure */
	#[\Override]
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$schema = $schemaClosure();
		if ($schema->hasTable('libresign_file') && $schema->getTable('libresign_file')->hasColumn('callback')) {
			$this->backupFileCallbacks($output);
		}

		$this->backupLegacyWebhookUrl($output);
	}

	private function backupFileCallbacks(IOutput $output): void {
		$query = $this->connection->getQueryBuilder();
		$query->select('id', 'callback')
			->from('libresign_file')
			->where($query->expr()->nonEmptyString('callback'))
			->orderBy('id');

		$result = $query->executeQuery();
		try {
			$row = $result->fetchAssociative();
			if ($row === false) {
				return;
			}

			// Build the CSV before creating AppData, so CSV write failures cannot
			// persist a partial backup and leave the migration free to drop data.
			$handle = fopen('php://temp', 'w+');
			if ($handle === false) {
				throw new \RuntimeException('Could not open LibreSign callback backup buffer');
			}

			try {
				if (fputcsv($handle, ['id', 'callback'], ',', '"', '') === false) {
					throw new \RuntimeException('Could not write LibreSign callback backup header');
				}

				do {
					if (fputcsv($handle, [$row['id'], $row['callback']], ',', '"', '') === false) {
						throw new \RuntimeException('Could not write LibreSign callback backup row');
					}
				} while (($row = $result->fetchAssociative()) !== false);

				$bytes = ftell($handle);
				if ($bytes === false || !rewind($handle)) {
					throw new \RuntimeException('Could not prepare LibreSign callback backup for storage');
				}

				// Supplying the populated resource writes synchronously. In contrast,
				// newFile() without content defers persistence until stream close.
				$folder = $this->appData->getFolder('/');
				$file = $folder->newFile(self::FILE_BACKUP, $handle);
				if ($file->getSize() != $bytes) {
					throw new \RuntimeException('Could not verify LibreSign callback backup size');
				}
				$output->info(
					'Legacy callback destinations saved to appdata_<instanceid>/libresign/'
					. self::FILE_BACKUP . ' (may contain credentials; restrict access).',
				);
			} finally {
				fclose($handle);
			}
		} finally {
			$result->closeCursor();
		}
	}

	private function backupLegacyWebhookUrl(IOutput $output): void {
		if (!in_array('webhook_sign_url', $this->appConfig->getKeys(Application::APP_ID), true)) {
			return;
		}

		$url = $this->appConfig->getValueString(Application::APP_ID, 'webhook_sign_url');
		if ($url === '') {
			return;
		}

		$content = json_encode(['webhook_sign_url' => $url], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		$folder = $this->appData->getFolder('/');
		$file = $folder->newFile(self::CONFIG_BACKUP, $content);
		if ($file->getSize() != strlen($content)) {
			throw new \RuntimeException('Could not verify LibreSign webhook URL backup size');
		}
		$output->info(
			'Legacy webhook URL saved to appdata_<instanceid>/libresign/'
			. self::CONFIG_BACKUP . ' (may contain credentials; restrict access).',
		);
	}

	/** @param Closure(): ISchemaWrapper $schemaClosure */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('libresign_file')) {
			return null;
		}

		$table = $schema->getTable('libresign_file');
		if (!$table->hasColumn('callback')) {
			return null;
		}

		$table->dropColumn('callback');
		return $schema;
	}

	/** @param Closure(): ISchemaWrapper $schemaClosure */
	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if (in_array('webhook_sign_url', $this->appConfig->getKeys(Application::APP_ID), true)) {
			$this->appConfig->deleteKey(Application::APP_ID, 'webhook_sign_url');
		}
	}
}
