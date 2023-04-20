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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version8000Date20230420125331 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper */
		$schema = $schemaClosure();
		$fileUser = $schema->getTable('libresign_file_user');
		$changed = false;
		if ($fileUser->hasColumn('identify_method')) {
			$fileUser->dropColumn('identify_method');
			$changed = true;
		}
		$libresignFileUser = $schema->getTable('libresign_file_user');
		if ($libresignFileUser->hasColumn('code')) {
			$libresignFileUser->dropColumn('code');
			$changed = true;
		}
		if (!$schema->hasTable('libresign_identify_method')) {
			$identifyMethod = $schema->createTable('libresign_identify_method');
			$identifyMethod->addColumn('file_user_id', Types::BIGINT, [
				'notnull' => true,
			]);
			$identifyMethod->addColumn('method', Types::STRING, [
				'notnull' => true,
				'default' => 'nextcloud',
				'length' => 30,
			]);
			$identifyMethod->addColumn('default', Types::SMALLINT, [
				'notnull' => true,
				'default' => 0,
				'length' => 1,
			]);
			$identifyMethod->addColumn('code', Types::STRING, [
				'notnull' => false,
				'length' => 256,
			]);
			$identifyMethod->addColumn('attemps', Types::SMALLINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$identifyMethod->addColumn('identified_at_date', Types::DATETIME, [
				'notnull' => false,
				'length' => 20,
				'unsigned' => true,
			]);
			$identifyMethod->addColumn('last_attempt_date', Types::DATETIME, [
				'notnull' => false,
				'length' => 20,
				'unsigned' => true,
			]);
			$identifyMethod->addUniqueIndex(['file_user_id', 'method'], 'identify_method_unique_index');
			$identifyMethod->addUniqueIndex(['file_user_id', 'default'], 'identify_default_unique_index');
			$changed = true;
		}
		if ($changed) {
			return $schema;
		}
		return null;
	}
}
