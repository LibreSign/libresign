<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Contract;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;

interface IPolicyResolver {
	public function resolve(IPolicyDefinition $definition, PolicyContext $context): ResolvedPolicy;

	/** @param list<IPolicyDefinition> $definitions
	 * @return array<string, ResolvedPolicy>
	 */
	public function resolveMany(array $definitions, PolicyContext $context): array;
}
