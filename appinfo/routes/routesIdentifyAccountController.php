<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v1)',
];

return [
	'ocs' => [
		['name' => 'IdentifyAccount#search', 'url' => '/api/{apiVersion}/identify-account/search', 'verb' => 'GET', 'requirements' => $requirements],
	],
];
