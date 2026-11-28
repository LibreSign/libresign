<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Migration;

use Doctrine\DBAL\Schema\Schema;
use OC\DB\Schema\Table as WrappedTable;
use OCA\Libresign\Migration\Version19006Date20261121170000;
use OCA\Libresign\Migration\Version19009Date20261124223000;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/** Tests the final schema and upgrades from the PR's earlier, unpublished steps. */
final class Version19009Date20261124223000Test extends TestCase {
	/** @return array{ISchemaWrapper, Schema} */
	private function schema(): array {
		$schema = new Schema();
		$wrapped = $this->createMock(ISchemaWrapper::class);
		$wrapped->method('hasTable')->willReturnCallback(
			static fn (string $name): bool => $schema->hasTable('oc_' . $name),
		);
		$wrapped->method('getTable')->willReturnCallback(
			static fn (string $name): WrappedTable => new WrappedTable($schema->getTable('oc_' . $name)),
		);
		$wrapped->method('createTable')->willReturnCallback(
			static fn (string $name): WrappedTable => new WrappedTable($schema->createTable('oc_' . $name)),
		);
		return [$wrapped, $schema];
	}

	private function createBaseIntegrationTable(ISchemaWrapper $schema): void {
		$original = new Version19006Date20261121170000();
		$output = $this->createMock(IOutput::class);
		self::assertSame($schema, $original->changeSchema($output, static fn (): ISchemaWrapper => $schema, []));
	}

	private function migrate(ISchemaWrapper $schema): ?ISchemaWrapper {
		return (new Version19009Date20261124223000())->changeSchema(
			$this->createMock(IOutput::class), static fn (): ISchemaWrapper => $schema, [],
		);
	}

	public function testFreshInstallationCreatesAllCscTablesAndConstraints(): void {
		[$wrapped, $schema] = $this->schema();
		$this->createBaseIntegrationTable($wrapped);
		self::assertSame($wrapped, $this->migrate($wrapped));

		$tables = [
			'libresign_signing_integration',
			'libresign_ext_sign_session',
			'libresign_ext_sign_private',
			'libresign_ext_sign_audit',
			'libresign_ext_sign_attempt',
		];
		foreach ($tables as $table) {
			self::assertTrue($schema->hasTable('oc_' . $table), $table);
		}
		$session = $schema->getTable('oc_libresign_ext_sign_session');
		foreach (['id', 'public_id', 'attempt_key', 'signer_slot', 'sign_request_id',
			'integration_id', 'integration_revision', 'document_sha256', 'prepared_enc',
			'signature_enc', 'status', 'expires_at'] as $column) {
			self::assertTrue($session->hasColumn($column), $column);
		}
		foreach ([
			'ls_ext_ss_public_uidx' => ['public_id'],
			'ls_ext_ss_attempt_uidx' => ['attempt_key'],
			'ls_ext_ss_slot_uidx' => ['signer_slot'],
			'ls_ext_ss_req_uidx' => ['sign_request_id'],
		] as $index => $columns) {
			self::assertTrue($session->hasIndex($index), $index);
			self::assertTrue($session->getIndex($index)->isUnique(), $index);
			self::assertSame($columns, $session->getIndex($index)->getColumns(), $index);
		}
		$attempt = $schema->getTable('oc_libresign_ext_sign_attempt');
		self::assertTrue($attempt->getIndex('ls_ext_ss_one_sign_uidx')->isUnique());
		self::assertTrue($attempt->getIndex('ls_ext_ss_op_uidx')->isUnique());
		$private = $schema->getTable('oc_libresign_ext_sign_private');
		self::assertTrue($private->hasColumn('secrets_enc'));
		$audit = $schema->getTable('oc_libresign_ext_sign_audit');
		self::assertTrue($audit->hasIndex('ls_ext_ss_audit_idx'));

		$integration = $schema->getTable('oc_libresign_signing_integration');
		self::assertSame('system', $integration->getColumn('scope_type')->getDefault());
		self::assertSame('', $integration->getColumn('scope_id')->getDefault());
		self::assertSame(['scope_type', 'scope_id', 'deleted_at', 'id'],
			$integration->getIndex('ls_sign_int_scope_idx')->getColumns());

		self::assertNull($this->migrate($wrapped), 'Re-running the consolidated migration must be a no-op');
	}

	public function testUpgradeFromEarlierStagedMigrationsPreservesExistingTables(): void {
		[$wrapped, $schema] = $this->schema();
		$this->createBaseIntegrationTable($wrapped);

		// Simulate the earlier 19007 state: a session exists, but the
		// 19008 sign-request uniqueness constraint has not yet been applied.
		$session = $schema->createTable('oc_libresign_ext_sign_session');
		$session->addColumn('sign_request_id', 'integer', ['notnull' => true]);
		$session->addColumn('prepared_enc', 'text', ['notnull' => true]);
		$session->addIndex(['sign_request_id'], 'old_session_lookup_idx');
		$private = $schema->createTable('oc_libresign_ext_sign_private');
		$private->addColumn('secrets_enc', 'text', ['notnull' => true]);

		self::assertSame($wrapped, $this->migrate($wrapped));
		self::assertTrue($session->hasIndex('old_session_lookup_idx'), 'Preserve existing indices');
		self::assertTrue($session->getIndex('ls_ext_ss_req_uidx')->isUnique());
		self::assertTrue($private->hasColumn('secrets_enc'), 'Preserve existing encrypted data columns');
		self::assertTrue($schema->hasTable('oc_libresign_ext_sign_audit'));
		self::assertTrue($schema->hasTable('oc_libresign_ext_sign_attempt'));
		self::assertSame('system', $schema->getTable('oc_libresign_signing_integration')
			->getColumn('scope_type')->getDefault());

		self::assertNull($this->migrate($wrapped));
	}

	public function testAlreadyAppliedRequestIndexIsNotCreatedTwice(): void {
		[$wrapped, $schema] = $this->schema();
		$this->createBaseIntegrationTable($wrapped);
		$session = $schema->createTable('oc_libresign_ext_sign_session');
		$session->addColumn('sign_request_id', 'integer', ['notnull' => true]);
		$session->addUniqueIndex(['sign_request_id'], 'ls_ext_ss_req_uidx');

		self::assertSame($wrapped, $this->migrate($wrapped));
		self::assertSame(['sign_request_id'], $session->getIndex('ls_ext_ss_req_uidx')->getColumns());
		self::assertNull($this->migrate($wrapped));
	}

	public function testDatabaseAllowsDifferentRequestsButRejectsDuplicateSigningSessions(): void {
		[$wrapped, $schema] = $this->schema();
		$this->createBaseIntegrationTable($wrapped);
		$this->migrate($wrapped);

		// SQLite in memory tests the actual generated uniqueness constraint,
		// without touching the Nextcloud database or persistent test fixtures.
		$db = new \PDO('sqlite::memory:');
		$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		foreach ($schema->toSql(new \Doctrine\DBAL\Platforms\SqlitePlatform()) as $statement) {
			$db->exec($statement);
		}
		$fields = [
			'public_id' => str_repeat('a', 32),
			'attempt_key' => str_repeat('a', 64),
			'signer_slot' => str_repeat('a', 64),
			'actor_id' => 'alice',
			'sign_request_id' => 123,
			'integration_id' => str_repeat('c', 32),
			'integration_revision' => 1,
			'credential_id' => 'synthetic-credential',
			'certificate_sha256' => str_repeat('d', 64),
			'document_revision' => 'revision-1',
			'document_sha256' => str_repeat('e', 64),
			'prepared_enc' => 'encrypted fixture',
			'status' => 'preparing',
			'expires_at' => '2026-12-01 12:00:00',
			'created_at' => '2026-11-24 12:00:00',
			'updated_at' => '2026-11-24 12:00:00',
		];
		$columns = array_keys($fields);
		$statement = $db->prepare('INSERT INTO oc_libresign_ext_sign_session ('
			. implode(', ', $columns) . ') VALUES ('
			. implode(', ', array_fill(0, count($columns), '?')) . ')');
		self::assertSame(1, $statement->execute(array_values($fields)) ? $statement->rowCount() : 0);

		// Different SignRequests remain independently signable.
		$fields['public_id'] = str_repeat('b', 32);
		$fields['attempt_key'] = str_repeat('b', 64);
		$fields['signer_slot'] = str_repeat('b', 64);
		$fields['sign_request_id'] = 124;
		self::assertTrue($statement->execute(array_values($fields)));

		// Changing signer identity and attempt must not allow a second
		// external signing operation for the original SignRequest.
		$fields['public_id'] = str_repeat('f', 32);
		$fields['attempt_key'] = str_repeat('f', 64);
		$fields['signer_slot'] = str_repeat('f', 64);
		$fields['actor_id'] = 'guest-bob';
		$fields['sign_request_id'] = 123;
		$this->expectException(\PDOException::class);
		$statement->execute(array_values($fields));
	}

	public function testMissingBaseMigrationIsRejectedInsteadOfCreatingOrphanedSessions(): void {
		[$wrapped, $schema] = $this->schema();
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage('19006 must run first');
		try {
			$this->migrate($wrapped);
		} finally {
			self::assertSame([], $schema->getTables());
		}
	}
}
