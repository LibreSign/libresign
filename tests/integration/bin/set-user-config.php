<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCP\Config\IUserConfig;

if ($argc !== 6) {
	fwrite(STDERR, "Usage: php set-user-config.php <nextcloud-root> <user> <app> <key> <value>\n");
	exit(1);
}

[, $nextcloudRoot, $user, $app, $key, $value] = $argv;

require_once $nextcloudRoot . '/lib/base.php';

$userConfig = \OCP\Server::get(IUserConfig::class);
$userConfig->setValueString($user, $app, $key, $value);
