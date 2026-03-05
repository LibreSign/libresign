<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Libresign\Migration;

use Closure;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version2040Date20211030204227 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
		$schema = $schemaClosure();
		/** @var Table */
		$libresignFile = $schema->getTable('libresign_file');
		if (!$libresignFile->hasColumn('status')) {
			$libresignFile->dropColumn('enabled');
			$libresignFile->addColumn('status', 'smallint', [
				'notnull' => true,
				'length' => 1,
			]);
		}
		$libresignFileUser = $schema->getTable('libresign_file_user');
		if (!$libresignFileUser->hasColumn('code')) {
			$libresignFileUser->addColumn('code', Types::STRING, [
				'notnull' => false,
				'length' => 256,
			]);
		}
		return $schema;
	}
}
