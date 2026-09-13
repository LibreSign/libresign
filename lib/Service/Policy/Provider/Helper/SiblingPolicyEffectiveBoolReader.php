<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\Helper;

use OCA\Libresign\Service\Policy\PolicyService;

/**
 * Reads a sibling policy's effective boolean without recursively nesting meta
 * resolution when two policies inspect each other.
 */
class SiblingPolicyEffectiveBoolReader {
	private int $depth = 0;

	public function getEffectiveBool(string $policyKey): bool {
		if ($this->depth > 0) {
			return false;
		}

		$this->depth++;
		try {
			return \OCP\Server::get(PolicyService::class)
				->resolve($policyKey)
				->getEffectiveValueAsBool();
		} finally {
			$this->depth--;
		}
	}
}
