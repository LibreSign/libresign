<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 * Router script for Playwright E2E tests
 * Routes requests to the appropriate Nextcloud entry point
 *
 * Used by: .github/workflows/playwright.yml
 * When running the PHP built-in server for E2E testing
 */

$rootDir = dirname(dirname(dirname(dirname(__FILE__))));

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = $rootDir . $uri;

// Serve static files as-is (except PHP files)
if ($uri !== '/' && file_exists($file) && !is_dir($file) && pathinfo($uri, PATHINFO_EXTENSION) !== 'php') {
	return false;
}

$dispatch = function (string $script, string $uri) use ($rootDir): void {
	$_SERVER['SCRIPT_NAME'] = $script;
	$_SERVER['SCRIPT_FILENAME'] = $rootDir . $script;
	$_SERVER['PHP_SELF'] = $script;
	$_SERVER['PATH_INFO'] = substr($uri, strlen($script)) ?: '';
	require $rootDir . $script;
};

if (str_starts_with($uri, '/ocs/')) {
	$dispatch('/ocs/v2.php', $uri);
} elseif (str_starts_with($uri, '/remote.php')) {
	$dispatch('/remote.php', $uri);
} elseif (str_starts_with($uri, '/public.php')) {
	$dispatch('/public.php', $uri);
} elseif (str_starts_with($uri, '/status.php')) {
	$dispatch('/status.php', $uri);
} else {
	$dispatch('/index.php', $uri);
}
