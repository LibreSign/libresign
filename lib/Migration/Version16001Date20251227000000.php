<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Normalize file names by removing extensions from the name field
 * - Removes file extensions from libresign_file.name based on metadata['extension']
 * - After this migration, the removeExtensionFromName method is no longer needed
 */
class Version16001Date20251227000000 extends SimpleMigrationStep {
	public function __construct(
		private \OCP\IDBConnection $connection,
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
		// No schema changes needed, only data migration
		return null;
	}

	/**
	 * Migrate file names by removing extensions
	 */
	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qb = $this->connection->getQueryBuilder();

		$qb->select('id', 'name', 'metadata')
			->from('libresign_file')
			->where($qb->expr()->isNotNull('metadata'));

		$cursor = $qb->executeQuery();
		$filesToUpdate = [];

		while ($row = $cursor->fetch()) {
			$metadata = json_decode($row['metadata'], true);

			// Only process files that have an extension in metadata
			if (!isset($metadata['extension']) || empty($metadata['extension'])) {
				continue;
			}

			$name = $row['name'];
			$extension = $metadata['extension'];

			// Remove the extension from the name
			$extensionPattern = '/\.' . preg_quote($extension, '/') . '$/i';
			$newName = preg_replace($extensionPattern, '', $name);

			// Only update if the name actually changed
			if ($newName !== $name) {
				$filesToUpdate[] = [
					'id' => (int)$row['id'],
					'newName' => $newName,
				];
			}
		}
		$cursor->closeCursor();

		// Update all files with normalized names
		if (!empty($filesToUpdate)) {
			foreach ($filesToUpdate as $file) {
				$updateQb = $this->connection->getQueryBuilder();

				$updateQb->update('libresign_file')
					->set('name', $updateQb->createNamedParameter($file['newName']))
					->where($updateQb->expr()->eq('id', $updateQb->createNamedParameter($file['id'], \OCP\DB\Types::INTEGER)))
					->executeStatement();
			}

			$output->info('Normalized ' . count($filesToUpdate) . ' file names by removing extensions from database');
		} else {
			$output->info('No file names needed normalization');
		}
	}
}
