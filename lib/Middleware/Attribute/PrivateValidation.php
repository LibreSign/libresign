<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Middleware\Attribute;

use Attribute;

#[Attribute]
class PrivateValidation {
	public function __construct(
		private bool $allowValidSignRequestUuid = false,
	) {
	}

	public function allowValidSignRequestUuid(): bool {
		return $this->allowValidSignRequestUuid;
	}
}
