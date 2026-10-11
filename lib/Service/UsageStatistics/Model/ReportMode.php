<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\UsageStatistics\Model;

enum ReportMode: string {
	/** The most recent closed period: current environment snapshots are included. */
	case CURRENT = 'current';
	/** A past period rebuilt from stored events: snapshots are unknown and omitted. */
	case HISTORICAL = 'historical';
}
