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
		$return = [];
		foreach ($list as $items) {
			foreach ($items as $item) {
				if (in_array($item['value']['shareWith'], $ids)) {
					continue;
				}
				$ids[] = $item['value']['shareWith'];
				$return[] = $item;
			}
		}
		return $return;
	}

	public function excludeEmpty(array $list): array {
		return array_filter($list, fn ($result) => strlen((string)$result['value']['shareWith']) > 0);
	}

	public function excludeNotAllowed(array $list): array {
		return array_filter($list, fn ($result) => isset($result['method']) && !empty($result['method']));
	}
}
