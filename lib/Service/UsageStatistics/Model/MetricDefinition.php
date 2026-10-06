<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\UsageStatistics\Model;

final class MetricDefinition {
	public const KIND_SNAPSHOT = 'snapshot';
	public const KIND_PERIOD = 'period';

	public function __construct(
		public readonly string $category,
		public readonly string $key,
		public readonly string $type,
		public readonly string $kind,
		public readonly string $aggregation,
		public readonly string $description,
		public readonly bool $required,
	) {
	}

	public function id(): string {
		return $this->category . '.' . $this->key;
	}

	public function isSnapshot(): bool {
		return $this->kind === self::KIND_SNAPSHOT;
	}
}
