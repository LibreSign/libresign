<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Exception;

/** Optimistic integration revision conflict, mapped to HTTP 409. */
final class ExternalIntegrationConflictException extends \RuntimeException {
	public function __construct() {
		parent::__construct('Integration changed concurrently');
	}
}
