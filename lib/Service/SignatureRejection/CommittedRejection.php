<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureRejection;

use OCA\Libresign\Db\SignRequest as SignRequestEntity;

final readonly class CommittedRejection {
	/** @param list<SignRequestEntity> $activatedSigners */
	public function __construct(
		public int $previousRootStatus,
		public array $activatedSigners,
	) {
	}
}
