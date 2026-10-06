<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\UsageStatistics\Model;

final class CollectedMetric {
	public function __construct(
		public readonly string $category,
		public readonly string $key,
		public readonly string $type,
		public readonly int|bool|string $value,
	) {
	}

	/** @return array{category: string, key: string, type: string, value: int|bool|string} */
	public function toArray(): array {
		return [
			'category' => $this->category,
			'key' => $this->key,
			'type' => $this->type,
			'value' => $this->value,
		];
	}
}
