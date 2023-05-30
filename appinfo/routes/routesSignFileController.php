<?php

$requirements = [
	'apiVersion' => 'v1',
];

return [
	'ocs' => [
		['name' => 'signFile#signUsingUuid',      'url' => '/api/{apiVersion}/sign/uuid/{uuid}', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'signFile#signUsingFileId',    'url' => '/api/{apiVersion}/sign/file_id/{fileId}', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'signFile#getCodeUsingUuid',   'url' => '/api/{apiVersion}/sign/uuid/{uuid}/code', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'signFile#getCodeUsingFileId', 'url' => '/api/{apiVersion}/sign/file_id/{fileId}/code', 'verb' => 'POST', 'requirements' => $requirements],
	],
];
