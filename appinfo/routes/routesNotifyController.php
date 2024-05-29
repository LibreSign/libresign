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
		['name' => 'notify#signer', 'url' => '/api/{apiVersion}/notify/signer', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'notify#signers', 'url' => '/api/{apiVersion}/notify/signers', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'notify#notificationDismiss', 'url' => '/api/{apiVersion}/notif/notification', 'verb' => 'DELETE', 'requirements' => $requirements],
	],
];
