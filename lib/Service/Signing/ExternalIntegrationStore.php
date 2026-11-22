<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Persists configured external signing integrations.
 *
 * Soft deletion disables an integration, removes its configuration and encrypted
 * credentials, and retains its identity and lifecycle metadata for auditing.
 */
final class ExternalIntegrationStore {
	private const TABLE = 'libresign_signing_integration';
	private const MAX_CONFIGURATION_BYTES = 32768;

	public function __construct(
		private IDBConnection $db,
		private ExternalIntegrationSecrets $secrets,
	) {
	}

	/**
	 * @param array<string, mixed> $validatedConfiguration Public fields validated by the administration service.
	 * @param array<string, string> $validatedSecrets Only secret fields accepted by the driver catalog.
	 */
	public function create(
		string $driverId,
		string $name,
		#[\SensitiveParameter] array $validatedConfiguration,
		#[\SensitiveParameter] array $validatedSecrets,
	): ExternalIntegrationRecord {
		$this->validateIdentity($driverId, $name);
		$configJson = $this->encodeConfiguration($validatedConfiguration);
		$encrypted = $this->secrets->encrypt($validatedSecrets);
		$publicId = bin2hex(random_bytes(16));
		$now = $this->now();
		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::TABLE)
			->values([
				'public_id' => $qb->createNamedParameter($publicId),
				'driver_id' => $qb->createNamedParameter($driverId),
				'name' => $qb->createNamedParameter(trim($name)),
				'configuration' => $qb->createNamedParameter($configJson),
				'secrets_enc' => $qb->createNamedParameter($encrypted),
				'enabled' => $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				'revision' => $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT),
				'created_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE),
				'updated_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE),
			])
			->executeStatement();
		return $this->get($publicId);
	}

	/**
	 * @return ExternalIntegrationRecord|null Null for missing or deleted integrations.
	 */
	public function find(string $publicId): ?ExternalIntegrationRecord {
		$row = $this->loadRow($publicId, false);
		return $row === null ? null : $this->toRecord($row);
	}

	/** @return ExternalIntegrationRecord */
	public function get(string $publicId): ExternalIntegrationRecord {
		return $this->find($publicId) ?? throw new \OutOfBoundsException('Integration not found');
	}

	/**
	 * Return only names of configured secret fields for a trusted admin caller.
	 * Secret values never leave this storage boundary through this method.
	 *
	 * @return list<string>
	 */
	public function configuredSecretKeys(string $publicId): array {
		$row = $this->loadRow($publicId, false);
		if ($row === null) {
			throw new \OutOfBoundsException('Integration not found');
		}
		return array_keys($this->secrets->decrypt((string)$row['secrets_enc']));
	}

	/**
	 * Keyset pagination is explicit, rather than silently truncating a list.
	 *
	 * @return array{items: list<ExternalIntegrationRecord>, nextCursor: ?int}
	 */
	public function listPage(int $afterId = 0, int $pageSize = 50): array {
		if ($afterId < 0 || $pageSize < 1 || $pageSize > 200) {
			throw new \InvalidArgumentException('Invalid integration page');
		}
		$qb = $this->db->getQueryBuilder();
		$rows = $qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->isNull('deleted_at'))
			->andWhere($qb->expr()->gt('id', $qb->createNamedParameter($afterId, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'ASC')
			->setMaxResults($pageSize + 1)
			->executeQuery()->fetchAllAssociative();

		$hasNext = count($rows) > $pageSize;
		$rows = array_slice($rows, 0, $pageSize);
		return [
			'items' => array_map($this->toRecord(...), $rows),
			'nextCursor' => $hasNext ? (int)$rows[count($rows) - 1]['id'] : null,
		];
	}

	/**
	 * Apply changes with optimistic concurrency. Omitted secret keys are not
	 * decrypted or rewritten. A non-empty secret patch can replace or explicitly
	 * remove individual keys after the driver catalog permits the operation.
	 *
	 * @param array<string, mixed> $validatedConfiguration
	 * @param array<string, string|null> $validatedSecretChanges
	 */
	public function update(
		string $publicId,
		int $expectedRevision,
		string $name,
		#[\SensitiveParameter] array $validatedConfiguration,
		#[\SensitiveParameter] array $validatedSecretChanges = [],
	): ExternalIntegrationRecord {
		if ($expectedRevision < 1) {
			throw new \InvalidArgumentException('Invalid integration revision');
		}
		$this->validateName($name);
		$configJson = $this->encodeConfiguration($validatedConfiguration);
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('name', $qb->createNamedParameter(trim($name)))
			->set('configuration', $qb->createNamedParameter($configJson))
			->set('revision', $qb->createFunction('revision + 1'))
			->set('updated_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_MUTABLE));

		if ($validatedSecretChanges !== []) {
			$row = $this->loadRow($publicId, false);
			if ($row === null || (int)$row['revision'] !== $expectedRevision) {
				throw new \RuntimeException('Integration changed concurrently');
			}
			$encrypted = $this->secrets->applyChanges((string)$row['secrets_enc'], $validatedSecretChanges);
			$qb->set('secrets_enc', $qb->createNamedParameter($encrypted));
		}
		$changed = $qb->where($qb->expr()->eq('public_id', $qb->createNamedParameter($publicId)))
			->andWhere($qb->expr()->isNull('deleted_at'))
			->andWhere($qb->expr()->eq('revision', $qb->createNamedParameter($expectedRevision, IQueryBuilder::PARAM_INT)))
			->executeStatement();
		if ($changed !== 1) {
			throw new \RuntimeException('Integration changed concurrently');
		}
		return $this->get($publicId);
	}

	/** Set only the administrative enabled bit, never the global experimental flag. */
	public function setEnabled(string $publicId, int $expectedRevision, bool $enabled): ExternalIntegrationRecord {
		$this->writeLifecycle($publicId, $expectedRevision, $enabled, false);
		return $this->get($publicId);
	}

	/**
	 * Deletion preserves identity for audit but erases connection settings and
	 * credentials atomically; new sessions must not use deleted integrations.
	 */
	public function softDelete(string $publicId, int $expectedRevision): void {
		$this->writeLifecycle($publicId, $expectedRevision, false, true);
	}

	private function writeLifecycle(string $publicId, int $expectedRevision, bool $enabled, bool $delete): void {
		if ($expectedRevision < 1) {
			throw new \InvalidArgumentException('Invalid integration revision');
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('enabled', $qb->createNamedParameter($enabled ? 1 : 0, IQueryBuilder::PARAM_INT))
			->set('revision', $qb->createFunction('revision + 1'))
			->set('updated_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_MUTABLE));
		if ($delete) {
			$qb->set('deleted_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_MUTABLE))
				->set('configuration', $qb->createNamedParameter('{}'))
				->set('secrets_enc', $qb->createNamedParameter(''));
		}
		$changed = $qb->where($qb->expr()->eq('public_id', $qb->createNamedParameter($publicId)))
			->andWhere($qb->expr()->isNull('deleted_at'))
			->andWhere($qb->expr()->eq('revision', $qb->createNamedParameter($expectedRevision, IQueryBuilder::PARAM_INT)))
			->executeStatement();
		if ($changed !== 1) {
			throw new \RuntimeException('Integration changed concurrently');
		}
	}

	/**
	 * Internal diagnostic of a tombstone's persisted reference, not an API method.
	 * This returns no plaintext secret material even for deleted entries.
	 */
	public function findRetainedReference(string $publicId): ?ExternalIntegrationRecord {
		$row = $this->loadRow($publicId, true);
		return $row === null ? null : $this->toRecord($row);
	}

	/** @return array<string, mixed>|null */
	private function loadRow(string $publicId, bool $includeDeleted): ?array {
		if (preg_match('/^[0-9a-f]{32}$/D', $publicId) !== 1) {
			return null;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('public_id', $qb->createNamedParameter($publicId)));
		if (!$includeDeleted) {
			$qb->andWhere($qb->expr()->isNull('deleted_at'));
		}
		$row = $qb->executeQuery()->fetchAssociative();
		return $row === false ? null : $row;
	}

	/** @param array<string, mixed> $row */
	private function toRecord(array $row): ExternalIntegrationRecord {
		try {
			$payload = json_decode((string)$row['configuration'], false, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			throw new \UnexpectedValueException('Malformed external integration configuration');
		}
		if (!$payload instanceof \stdClass) {
			throw new \UnexpectedValueException('Malformed external integration configuration');
		}
		$config = (array)$payload;
		foreach (array_keys($config) as $key) {
			if (!is_string($key) || $key === '') {
				throw new \UnexpectedValueException('Malformed external integration configuration');
			}
		}
		return new ExternalIntegrationRecord(
			(string)$row['public_id'],
			(string)$row['driver_id'],
			(string)$row['name'],
			$config,
			(int)$row['enabled'] === 1 && $row['deleted_at'] === null,
			(string)$row['secrets_enc'] !== '',
			(int)$row['revision'],
			$this->iso8601((string)$row['created_at']),
			$this->iso8601((string)$row['updated_at']),
		);
	}

	private function iso8601(string $value): string {
		return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format(DATE_ATOM);
	}

	private function now(): \DateTime {
		return new \DateTime('now', new \DateTimeZone('UTC'));
	}

	private function validateIdentity(string $driverId, string $name): void {
		if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $driverId) !== 1) {
			throw new \InvalidArgumentException('Invalid integration driver');
		}
		$this->validateName($name);
	}

	private function validateName(string $name): void {
		$normalized = trim($name);
		if ($normalized === '' || !mb_check_encoding($normalized, 'UTF-8') || mb_strlen($normalized, 'UTF-8') > 128) {
			throw new \InvalidArgumentException('Invalid integration display name');
		}
	}

	/** @param array<string, mixed> $configuration */
	private function encodeConfiguration(#[\SensitiveParameter] array $configuration): string {
		foreach (array_keys($configuration) as $key) {
			if (!is_string($key) || $key === '') {
				throw new \InvalidArgumentException('Invalid integration configuration');
			}
		}
		try {
			$encoded = json_encode((object)$configuration, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			throw new \InvalidArgumentException('Invalid integration configuration');
		}
		if (strlen($encoded) > self::MAX_CONFIGURATION_BYTES) {
			throw new \InvalidArgumentException('Integration configuration too large');
		}
		return $encoded;
	}
}
