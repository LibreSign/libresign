<?php

declare(strict_types=1);

namespace OCA\Libresign\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version2040Date20210619135352 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = $schema->createTable('libresign_user_profile_file');
		$table->addColumn('user_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('file_type_id', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->addColumn('libresign_file_id', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->addIndex(['user_id']);
		$table->addIndex(['file_type_id']);
		$table->addUniqueIndex(['user_id', 'file_type_id'], 'libresign_user_file_type_index');

		$table = $schema->createTable('libresign_file_type');
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('type', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['type']);
		$table->addUniqueIndex(['type'], 'libresign_file_type_index');

		return $schema;
	}
}
