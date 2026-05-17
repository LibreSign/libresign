<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Identify;

class ResultFilter {
	public function unify(array $list): array {
		$ids = [];
		$scores = [];
		$return = [];
		foreach ($list as $items) {
			foreach ($items as $item) {
				$shareWith = $item['value']['shareWith'] ?? null;
				if ($shareWith === null) {
					continue;
				}
				$score = $this->scoreItem($item);
				$existingIndex = array_search($shareWith, $ids, true);
				if ($existingIndex !== false) {
					if ($score >= ($scores[$existingIndex] ?? -1)) {
						$return[$existingIndex] = $item;
						$scores[$existingIndex] = $score;
					}
					continue;
				}
				$ids[] = $shareWith;
				$scores[] = $score;
				$return[] = $item;
			}
		}
		return $return;
	}

	private function scoreItem(array $item): int {
		$label = (string)($item['label'] ?? '');
		$unique = (string)($item['shareWithDisplayNameUnique'] ?? '');
		$shareWith = (string)($item['value']['shareWith'] ?? '');

		if ($label === '') {
			return 0;
		}
		if ($label !== $unique && $label !== $shareWith) {
			return 2;
		}
		return 1;
	}

	public function excludeEmpty(array $list): array {
		return array_filter($list, fn ($result) => strlen((string)($result['value']['shareWith'] ?? '')) > 0);
	}

	public function excludeNotAllowed(array $list, array $allowedMethods = []): array {
		$allowedMethods = array_values(array_filter(array_map(
			static fn (mixed $method): string => is_string($method) ? trim($method) : '',
			$allowedMethods,
		), static fn (string $method): bool => $method !== ''));

		return array_filter($list, static function (array $result) use ($allowedMethods): bool {
			if (!isset($result['method']) || empty($result['method'])) {
				return false;
			}

			if ($allowedMethods === []) {
				return true;
			}

			return in_array((string)$result['method'], $allowedMethods, true);
		});
	}
}
