<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

/**
 * Manages external signing integrations using trusted driver metadata.
 *
 * Input validation and secret redaction are applied before persisting changes
 * or producing administrative read models.
 */
final class ExternalIntegrationAdministration {
	public function __construct(
		private ExternalIntegrationStore $store,
		private ExternalIntegrationFieldValidator $validator,
	) {
	}

	/**
	 * @param array<string, mixed> $driver Trusted declarative catalog entry.
	 * @param array<string, mixed> $configuration Public configuration only.
	 * @param array<string, string> $secrets New secret values, never public configuration.
	 */
	public function create(
		array $driver,
		string $name,
		#[\SensitiveParameter] array $configuration,
		#[\SensitiveParameter] array $secrets = [],
		string $scopeType = 'system',
		string $scopeId = '',
	): ExternalIntegrationRecord {
		[$driverId, $fields] = $this->metadata($driver);
		$this->validator->validateCreate($fields, $configuration, $secrets);
		return $this->store->create($driverId, $name, $configuration, $secrets, $scopeType, $scopeId);
	}

	/**
	 * A null configuration or name preserves the current value. Secret patches
	 * preserve omitted keys and allow explicit null only for optional fields.
	 *
	 * @param array<string, mixed> $driver Trusted declarative catalog entry.
	 * @param array<string, mixed>|null $configuration Full replacement public configuration.
	 * @param array<string, string|null> $secretChanges
	 */
	public function update(
		array $driver,
		string $publicId,
		int $expectedRevision,
		?string $name = null,
		#[\SensitiveParameter] ?array $configuration = null,
		#[\SensitiveParameter] array $secretChanges = [],
	): ExternalIntegrationRecord {
		[$driverId, $fields] = $this->metadata($driver);
		$current = $this->requireDriver($publicId, $driverId);
		$nextConfiguration = $configuration ?? $current->configuration;
		$this->validator->validateUpdate($fields, $nextConfiguration, $secretChanges);
		$secretKeys = [];
		foreach ($fields as $field) {
			if ($field['secret'] === true) {
				$secretKeys[] = $field['key'];
			}
		}
		$replaceAllSecrets = $secretKeys !== [] && count(array_diff($secretKeys, array_keys($secretChanges))) === 0;
		// A full secret replacement needs no access to the previous ciphertext.
		$needsExistingSecrets = false;
		foreach ($fields as $field) {
			if ($field['secret'] === true && $field['required'] === true
				&& !array_key_exists($field['key'], $secretChanges)) {
				$needsExistingSecrets = true;
			}
		}
		$configured = $needsExistingSecrets
			? array_fill_keys($this->store->configuredSecretKeys($publicId), true) : [];
		foreach ($fields as $field) {
			if ($field['secret'] === true && $field['required'] === true) {
				$key = $field['key'];
				if (!array_key_exists($key, $secretChanges) && !isset($configured[$key])) {
					throw new \InvalidArgumentException('Required integration secret is not configured');
				}
			}
		}
		return $this->store->update($publicId, $expectedRevision, $name ?? $current->name, $nextConfiguration, $secretChanges, $replaceAllSecrets);
	}

	/**
	 * Enabling is administrative state only; it never overrides the global
	 * experimental gate or makes an unavailable driver operational.
	 *
	 * @param array<string, mixed> $driver Trusted declarative catalog entry.
	 */
	public function setEnabled(
		array $driver,
		string $publicId,
		int $expectedRevision,
		bool $enabled,
	): ExternalIntegrationRecord {
		[$driverId] = $this->metadata($driver);
		$this->requireDriver($publicId, $driverId);
		if ($enabled && ($driver['available'] ?? false) !== true) {
			throw new \DomainException('External signing driver is unavailable');
		}
		return $this->store->setEnabled($publicId, $expectedRevision, $enabled);
	}

	public function delete(string $publicId, int $expectedRevision): void {
		$this->store->softDelete($publicId, $expectedRevision);
	}

	public function get(string $publicId): ExternalIntegrationRecord {
		return $this->store->get($publicId);
	}

	/** @return array{items: list<ExternalIntegrationRecord>, nextCursor: ?int} */
	public function listPage(int $afterId = 0, int $pageSize = 50, ?string $scopeType = null, string $scopeId = ''): array {
		return $this->store->listPage($afterId, $pageSize, $scopeType, $scopeId);
	}

	/**
	 * Produces the administrative read model without exposing credentials.
	 * Secret fields report their configured state without returning values.
	 *
	 * @param array<string, mixed> $driver Trusted declarative catalog entry.
	 * @return array<string, mixed>
	 */
	public function describe(array $driver, string $publicId): array {
		[$driverId, $fields] = $this->metadata($driver);
		$record = $this->requireDriver($publicId, $driverId);
		try {
			$configured = array_fill_keys($this->store->configuredSecretKeys($publicId), true);
		} catch (\OCA\Libresign\Exception\ExternalIntegrationSecretException) {
			// Unreadable credentials must not block administrative recovery or
			// be reported as usable. Stored ciphertext remains until replaced.
			$configured = [];
		}
		$status = [];
		foreach ($fields as $field) {
			if ($field['secret'] === true) {
				$status[] = ['key' => $field['key'], 'isConfigured' => isset($configured[$field['key']])];
			}
		}
		$result = $record->jsonSerialize();
		$result['secretFields'] = $status;
		$result['driverAvailable'] = ($driver['available'] ?? false) === true;
		return $result;
	}

	/**
	 * @param array<string, mixed> $driver
	 * @return array{string, list<array<string, mixed>>}
	 */
	private function metadata(array $driver): array {
		$id = $driver['id'] ?? null;
		$fields = $driver['fields'] ?? null;
		if (!is_string($id) || preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $id) !== 1
			|| !is_array($fields) || !array_is_list($fields)) {
			throw new \InvalidArgumentException('Invalid external signing driver metadata');
		}
		return [$id, $fields];
	}

	private function requireDriver(string $publicId, string $driverId): ExternalIntegrationRecord {
		$record = $this->store->get($publicId);
		if ($record->driverId !== $driverId) {
			throw new \InvalidArgumentException('Integration driver cannot be changed');
		}
		return $record;
	}
}
