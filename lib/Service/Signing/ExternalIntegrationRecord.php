<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

/** This is deliberately safe to JSON-serialize: it never owns secret values. */
final readonly class ExternalIntegrationRecord implements \JsonSerializable {
	/** @param array<string, mixed> $configuration */
	public function __construct(
		public string $id,
		public string $driverId,
		public string $name,
		public array $configuration,
		public bool $enabled,
		public bool $hasSecrets,
		public int $revision,
		public string $createdAt,
		public string $updatedAt,
	) {
	}

	/** @return array{id: string, driverId: string, name: string, configuration: array<string, mixed>, enabled: bool, hasSecrets: bool, revision: int, createdAt: string, updatedAt: string} */
	#[\Override]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'driverId' => $this->driverId,
			'name' => $this->name,
			'configuration' => $this->configuration,
			'enabled' => $this->enabled,
			'hasSecrets' => $this->hasSecrets,
			'revision' => $this->revision,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
