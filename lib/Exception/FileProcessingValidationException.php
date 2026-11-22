<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Exception;

/**
 * An explicitly user-facing file validation error. Do not use for errors
 * containing internal exception messages or infrastructure diagnostics.
 */
final class FileProcessingValidationException extends LibresignException {
}
