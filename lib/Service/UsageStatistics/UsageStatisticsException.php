<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\UsageStatistics;

/**
 * Raised when a usage report cannot be produced safely. A report is never
 * sent with a metric silently missing or holding an invalid value.
 */
final class UsageStatisticsException extends \RuntimeException {
}
