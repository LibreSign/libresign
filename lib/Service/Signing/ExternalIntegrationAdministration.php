<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

/**
 * Shared backend operations for configured external signing integrations.
 *
 * Driver metadata must come from the trusted #9595 catalog. This service does
 * not authorize an HTTP caller or resolve the global experimental flag: the
 * administrative controller must enforce both boundaries before invoking it.
 * No external HTTP or signing operation is performed here.
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
	public function create(array $driver, string $name, array $configuration, #[\SensitiveParameter] array $secrets = []): ExternalIntegrationRecord {
		[$driverId, $fields] = $this->metadata($driver);
		$this->validator->validateCreate($fields, $configuration, $secrets);
		return $this->store->create($driverId, $name, $configuration, $secrets);
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
		?array $configuration = null,
		#[\SensitiveParameter] array $secretChanges = [],
	): ExternalIntegrationRecord {
		[$driverId, $fields] = $this->metadata($driver);
		$current = $this->requireDriver($publicId, $driverId);
		$nextConfiguration = $configuration ?? $current->configuration;
		$this->validator->validateUpdate($fields, $nextConfiguration, $secretChanges);
		$configured = array_fill_keys($this->store->configuredSecretKeys($publicId), true);
		foreach ($fields as $field) {
			if ($field['secret'] === true && $field['required'] === true) {
				$key = $field['key'];
				if (!array_key_exists($key, $secretChanges) && !isset($configured[$key])) {
					throw new \InvalidArgumentException('Required integration secret is not configured');
				}
			}
		}
		return $this->store->update($publicId, $expectedRevision, $name ?? $current->name, $nextConfiguration, $secretChanges);
	}

	/**
	 * Enabling is administrative state only; it never overrides the global
	 * experimental gate or makes an unavailable driver operational.
	 *
	 * @param array<string, mixed> $driver Trusted declarative catalog entry.
	 */
	public function setEnabled(array $driver, string $publicId, int $expectedRevision, bool $enabled): ExternalIntegrationRecord {
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
	public function listPage(int $afterId = 0, int $pageSize = 50): array {
		return $this->store->listPage($afterId, $pageSize);
	}

	/**
	 * A backend-only, secret-free detail view for the future admin OCS route.
	 * Read responses expose per-field presence, not ciphertext or token values.
	 *
	 * @param array<string, mixed> $driver Trusted declarative catalog entry.
	 * @return array<string, mixed>
	 */
	public function describe(array $driver, string $publicId): array {
		[$driverId, $fields] = $this->metadata($driver);
		$record = $this->requireDriver($publicId, $driverId);
		$configured = array_fill_keys($this->store->configuredSecretKeys($publicId), true);
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
