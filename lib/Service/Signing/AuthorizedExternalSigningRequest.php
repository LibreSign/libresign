<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;

/**
 * Server-trusted proof that the current request passed the existing signing
 * identity checks. Never accept or deserialize this object from client input.
 */
final readonly class AuthorizedExternalSigningRequest {
	public function __construct(
		public File $file,
		public SignRequest $signRequest,
		public string $actorId,
	) {
	}
}
