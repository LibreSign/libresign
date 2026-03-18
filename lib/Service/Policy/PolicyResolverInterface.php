<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

interface PolicyResolverInterface {
	public function resolve(string $policyKey, PolicyContext $context): ResolvedPolicy;

	/** @param list<string> $policyKeys
	 * @return array<string, ResolvedPolicy>
	 */
	public function resolveMany(array $policyKeys, PolicyContext $context): array;
}
