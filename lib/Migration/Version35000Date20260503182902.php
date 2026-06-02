<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * FIXME Auto-generated migration step: Please modify to your needs!
 */
class Version35000Date20260503182902 extends SimpleMigrationStep
{

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		$schema = $schemaClosure();

		if (!$schema->hasTable('gopaperless_dpo_mobile_options')) {

			$table = $schema->createTable('gopaperless_dpo_mobile_options');

			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);

			// Payment name (e.g. "mpesa", "airtel")
			$table->addColumn('provider', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			// Country (e.g. "kenya", "tanzania")
			$table->addColumn('country', 'string', [
				'notnull' => true,
				'length' => 32,
			]);

			// Country Code (e.g. "KE", "TZ")
			$table->addColumn('country_code', 'string', [
				'notnull' => false,
				'length' => 4,
			]);

			// Prefix (e.g. 254)
			$table->addColumn('prefix', 'string', [
				'notnull' => false,
				'length' => 16,
			]);

			$table->addColumn('currency', 'string', [
				'notnull' => false,
				'length' => 8,
			]);

			$table->addColumn('instructions', 'text', [
				'notnull' => false,
			]);

			$table->addColumn('logo', 'string', [
				'notnull' => false,
				'length' => 255,
			]);

			$table->addColumn('raw_payload', 'json', [
				'notnull' => false,
			]);

			$table->addColumn('created_at', 'datetime', [
				'notnull' => true,
			]);

			$table->addColumn('updated_at', 'datetime', [
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);

			$table->addIndex(['country'], 'idx_dpo_country');
			$table->addIndex(['provider'], 'idx_dpo_provider');
			$table->addIndex(['country_code'], 'idx_dpo_country_code');

			$table->addUniqueIndex(
				['country', 'prefix'],
				'uniq_dpo_option'
			);
		}

		return $schema;
	}
}
