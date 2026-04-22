<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Middleware\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class RequireFileAccess {
	public function __construct(
		private string $identifier = 'fileId',
	) {
	}

	public function getIdentifier(): string {
		return $this->identifier;
	}
}
