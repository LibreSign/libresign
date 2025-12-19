<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\Enum\NodeType;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add envelope support following DocuSign/Adobe Sign terminology
 * - node_type: file or envelope
 * - parent_file_id: parent-child relationship
 */
class Version16000Date20251218000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('libresign_file');

		if (!$table->hasColumn('node_type')) {
			$table->addColumn('node_type', Types::STRING, [
				'notnull' => true,
				'length' => 10,
				'default' => NodeType::FILE->value,
			]);
		}

		if (!$table->hasColumn('parent_file_id')) {
			$table->addColumn('parent_file_id', Types::INTEGER, [
				'notnull' => false,
				'default' => null,
			]);
		}

		if (!$table->hasIndex('libresign_file_parent_idx')) {
			$table->addIndex(['parent_file_id'], 'libresign_file_parent_idx');
		}

		if (!$table->hasIndex('libresign_file_node_type_idx')) {
			$table->addIndex(['node_type'], 'libresign_file_node_type_idx');
		}

		if (!$table->hasIndex('libresign_file_parent_type_idx')) {
			$table->addIndex(['parent_file_id', 'node_type'], 'libresign_file_parent_type_idx');
		}

		return $schema;
	}
}
