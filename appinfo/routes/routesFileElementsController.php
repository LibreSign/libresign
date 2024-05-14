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
		['name' => 'FileElement#post',   'url' => '/api/{apiVersion}/file-element/{uuid}', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'FileElement#patch',  'url' => '/api/{apiVersion}/file-element/{uuid}/{elementId}', 'verb' => 'PATCH', 'requirements' => $requirements],
		['name' => 'FileElement#delete', 'url' => '/api/{apiVersion}/file-element/{uuid}/{elementId}', 'verb' => 'DELETE', 'requirements' => $requirements],
	],
];
