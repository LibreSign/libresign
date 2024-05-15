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
		['name' => 'File#save',           'url' => '/api/{apiVersion}/file', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'File#list',           'url' => '/api/{apiVersion}/file/list', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'File#getThumbnail',   'url' => '/api/{apiVersion}/file/thumbnail/{nodeId}', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'File#validate',       'url' => '/api/{apiVersion}/file/validate/', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'File#validateUuid',   'url' => '/api/{apiVersion}/file/validate/uuid/{uuid}', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'File#validateFileId', 'url' => '/api/{apiVersion}/file/validate/file_id/{fileId}', 'verb' => 'GET', 'requirements' => $requirements],
	],
];
