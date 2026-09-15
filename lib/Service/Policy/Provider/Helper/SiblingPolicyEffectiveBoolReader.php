<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\Helper;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Runtime\DefaultPolicyResolver;
use OCA\Libresign\Service\Policy\Runtime\PolicyRegistry;
use OCA\Libresign\Service\Policy\Runtime\PolicySource;

/**
 * Reads a sibling policy's effective boolean in the same PolicyContext used to
 * build the current resolved state, without recursively nesting meta resolution
 * when two policies inspect each other.
 */
class SiblingPolicyEffectiveBoolReader {
	private int $depth = 0;
	private DefaultPolicyResolver $resolver;

	public function __construct(
		private PolicyRegistry $registry,
		PolicySource $source,
	) {
		$this->resolver = new DefaultPolicyResolver($source);
	}

	public function getEffectiveBool(string $policyKey, PolicyContext $context): bool {
		if ($this->depth > 0) {
			return false;
		}

		$this->depth++;
		try {
			return $this->resolver
				->resolve($this->registry->get($policyKey), $context)
				->getEffectiveValueAsBool();
		} finally {
			$this->depth--;
		}
	}
}
