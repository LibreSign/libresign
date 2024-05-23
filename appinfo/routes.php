<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '{apiVersion}',
	'path' => '.+',
];

$routes = [
	'routes' => [
		// API
		['name' => 'api#preflighted_cors',            'url' => '/api/{apiVersion}/{path}', 'verb' => 'OPTIONS', 'requirements' => $requirements],
	],
];

return array_merge_recursive(
	$routes,
	include(__DIR__ . '/routes/routesAccountController.php'),
	include(__DIR__ . '/routes/routesAdminController.php'),
	include(__DIR__ . '/routes/routesDevelopController.php'),
	include(__DIR__ . '/routes/routesFileController.php'),
	include(__DIR__ . '/routes/routesFileElementsController.php'),
	include(__DIR__ . '/routes/routesIdentifyAccountController.php'),
	include(__DIR__ . '/routes/routesNotifyController.php'),
	include(__DIR__ . '/routes/routesPageController.php'),
	include(__DIR__ . '/routes/routesRequestSignatureController.php'),
	include(__DIR__ . '/routes/routesSettingsController.php'),
	include(__DIR__ . '/routes/routesSignatureElementsController.php'),
	include(__DIR__ . '/routes/routesSignFileController.php'),
);
