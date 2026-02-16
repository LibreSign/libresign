<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Middleware\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class RequireSignRequestUuid {
	public function __construct(
		protected bool $skipIfAuthenticated = false,
		protected bool $redirectIfSignedToValidation = false,
		protected bool $allowIdDocs = false,
		protected bool $allowFileUuid = false,
	) {
	}

	public function skipIfAuthenticated(): bool {
		return $this->skipIfAuthenticated;
	}

	public function redirectIfSignedToValidation(): bool {
		return $this->redirectIfSignedToValidation;
	}

	public function allowIdDocs(): bool {
		return $this->allowIdDocs;
	}

	public function allowFileUuid(): bool {
		return $this->allowFileUuid;
	}
}
