<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Exception;

/** Error explicitly classified as safe for the ID document upload response. */
final class IdDocsUploadValidationException extends LibresignException {
}
