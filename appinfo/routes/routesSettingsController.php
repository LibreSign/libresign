<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => 'v1',
];

return [
	'ocs' => [
		['name' => 'setting#hasRootCert', 'url' => '/api/{apiVersion}/setting/has-root-cert', 'verb' => 'GET', 'requirements' => $requirements],
	],
];
