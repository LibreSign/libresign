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
 * Reads the effective value of sibling policies in the same PolicyContext.
 *
 * A policy that only makes sense next to other keys needs to see them to
 * validate a change, and it must see them exactly as the layer being written
 * would: same user, same groups, same request overrides. The depth guard keeps
 * two policies that inspect each other from resolving in circles; a reentrant
 * read answers null so the caller falls back to its own default.
 */
class SiblingPolicyEffectiveValueReader {
	private int $depth = 0;
	private DefaultPolicyResolver $resolver;

	public function __construct(
		private PolicyRegistry $registry,
		PolicySource $source,
	) {
		$this->resolver = new DefaultPolicyResolver($source);
	}

	public function getEffectiveValue(string $policyKey, PolicyContext $context): mixed {
		if ($this->depth > 0) {
			return null;
		}

		$this->depth++;
		try {
			return $this->resolver
				->resolve($this->registry->get($policyKey), $context)
				->getEffectiveValue();
		} finally {
			$this->depth--;
		}
	}

	/**
	 * @param list<string> $policyKeys
	 * @return array<string, mixed>
	 */
	public function getEffectiveValues(array $policyKeys, PolicyContext $context): array {
		$values = [];
		foreach ($policyKeys as $policyKey) {
			$values[$policyKey] = $this->getEffectiveValue($policyKey, $context);
		}

		return $values;
	}
}
