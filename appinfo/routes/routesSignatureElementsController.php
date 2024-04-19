<?php

$requirements = [
	'apiVersion' => 'v1',
];

return [
	'ocs' => [
		['name' => 'SignatureElements#createSignatureElement',    'url' => '/api/{apiVersion}/signature/elements', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'SignatureElements#getSignatureElements',      'url' => '/api/{apiVersion}/signature/elements', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'SignatureElements#getSignatureElementPreview','url' => '/api/{apiVersion}/signature/elements/preview/{nodeId}', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'SignatureElements#getSignatureElement',       'url' => '/api/{apiVersion}/signature/elements/{nodeId}', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'SignatureElements#patchSignatureElement',     'url' => '/api/{apiVersion}/signature/elements/{nodeId}', 'verb' => 'PATCH', 'requirements' => $requirements],
		['name' => 'SignatureElements#deleteSignatureElement',    'url' => '/api/{apiVersion}/signature/elements/{nodeId}', 'verb' => 'DELETE', 'requirements' => $requirements],
	],
];
