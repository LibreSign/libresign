<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '{apiVersion}',
	'path' => '.+',
];

return [
	'routes' => [
		// API
		['name' => 'api#preflighted_cors',            'url' => '/api/{apiVersion}/{path}', 'verb' => 'OPTIONS', 'requirements' => $requirements],
	],
];
