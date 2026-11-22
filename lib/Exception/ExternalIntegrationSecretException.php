<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Exception;

/** A safe, distinguishable failure at the integration credential boundary. */
final class ExternalIntegrationSecretException extends \RuntimeException {
	public static function protectionFailed(): self {
		return new self('Integration secrets could not be protected');
	}

	public static function recoveryFailed(): self {
		return new self('Integration secrets could not be decrypted');
	}
}
