<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Middleware\Attribute;

use Attribute;

/**
 * Authorize any participant UUID (signer or observer) for read-only access.
 *
 * Use this for document/PDF viewing. Signing and other write endpoints must keep
 * {@see RequireSignRequestUuid}, which rejects observers.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class RequireParticipantUuid {
	public function __construct(
		protected bool $skipIfAuthenticated = false,
		protected bool $allowIdDocs = false,
	) {
	}

	public function skipIfAuthenticated(): bool {
		return $this->skipIfAuthenticated;
	}

	public function allowIdDocs(): bool {
		return $this->allowIdDocs;
	}
}
