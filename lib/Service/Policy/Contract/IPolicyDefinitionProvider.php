<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Contract;

interface IPolicyDefinitionProvider {
	/** @return list<string> */
	public function keys(): array;

	public function get(string|\BackedEnum $policyKey): IPolicyDefinition;
}
