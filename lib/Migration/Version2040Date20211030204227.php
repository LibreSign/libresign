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
