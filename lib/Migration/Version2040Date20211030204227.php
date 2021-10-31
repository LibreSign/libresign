<?php

declare(strict_types=1);

namespace OCA\Libresign\Migration;

use Closure;
use Doctrine\DBAL\Schema\Table;
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
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		/** @var Table */
		$table = $schema->getTable('libresign_file');
		$table->dropColumn('enabled');
		$table->addColumn('status', 'smallint', [
			'notnull' => true,
			'length' => 1,
		]);
		return $schema;
	}
}
