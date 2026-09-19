<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

require_once __DIR__ . '/ReleasePlanner.php';

use LibreSign\Release\ReleasePlanner;

$options = getopt('', ['current-version:', 'date:', 'prs:']);

$currentVersion = $options['current-version'] ?? null;
$date = $options['date'] ?? gmdate('Y-m-d');
$prsFile = $options['prs'] ?? null;

if (!is_string($currentVersion) || !is_string($prsFile)) {
	fwrite(STDERR, "Usage: php plan.php --current-version X.Y.Z --prs prs.json [--date YYYY-MM-DD]\n");
	exit(2);
}

$pullRequests = json_decode((string)file_get_contents($prsFile), true, flags: JSON_THROW_ON_ERROR);
$plan = (new ReleasePlanner())->plan($currentVersion, $pullRequests, (string)$date);

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
