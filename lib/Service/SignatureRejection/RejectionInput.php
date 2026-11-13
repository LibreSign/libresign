<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureRejection;

final readonly class RejectionInput {
	public function __construct(
		public ?string $comment,
		public bool $commentIsPrivate,
		public bool $cancelsWorkflow,
	) {
	}
}
