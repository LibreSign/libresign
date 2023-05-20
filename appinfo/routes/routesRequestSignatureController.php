<?php

$requirements = [
	'apiVersion' => 'v1',
];

return [
	'ocs' => [
		['name' => 'RequestSignature#request',                              'url' => '/api/{apiVersion}/request-signature', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'RequestSignature#updateSign',                           'url' => '/api/{apiVersion}/request-signature', 'verb' => 'PATCH', 'requirements' => $requirements],
		['name' => 'RequestSignature#deleteAllRequestSignatureUsingFileId', 'url' => '/api/{apiVersion}/sign/file_id/{fileId}', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'RequestSignature#deleteOneRequestSignatureUsingFileId', 'url' => '/api/{apiVersion}/sign/file_id/{fileId}/{fileUserId}', 'verb' => 'DELETE', 'requirements' => $requirements],
	],
];
