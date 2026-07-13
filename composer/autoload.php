<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$loader = require __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../lib/Bootstrap/UpgradeSafeAutoloader.php';

\OCA\Libresign\Bootstrap\UpgradeSafeAutoloader::register($loader, dirname(__DIR__));

return $loader;
