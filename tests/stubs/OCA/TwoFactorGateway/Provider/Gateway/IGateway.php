<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Minimal fallback stub for the optional Two-Factor Gateway app when it is not
 * present in the local Nextcloud checkout used for Psalm analysis.
 */

namespace OCA\TwoFactorGateway\Provider\Gateway {
	interface IGateway {
		public function send(string $identifier, string $message): void;

		public function isComplete(): bool;
	}
}
