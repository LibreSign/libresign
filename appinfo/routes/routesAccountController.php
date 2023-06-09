<?php

$requirements = [
	'apiVersion' => 'v1',
];

return [
	'ocs' => [
		['name' => 'account#createToSign',              'url' => '/api/{apiVersion}/account/create/{uuid}', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'account#me',                        'url' => '/api/{apiVersion}/account/me', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'account#updateSettings',            'url' => '/api/{apiVersion}/account/settings', 'verb' => 'PATCH', 'requirements' => $requirements],
		['name' => 'account#signatureGenerate',         'url' => '/api/{apiVersion}/account/signature', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'account#addFiles',                  'url' => '/api/{apiVersion}/account/files', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'account#deleteFile',                'url' => '/api/{apiVersion}/account/files', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'account#accountFileListToOwner',    'url' => '/api/{apiVersion}/account/files', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'account#accountFileListToApproval', 'url' => '/api/{apiVersion}/account/files/approval/list', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'account#createSignatureElement',    'url' => '/api/{apiVersion}/account/signature/elements', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'account#getSignatureElements',      'url' => '/api/{apiVersion}/account/signature/elements', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'account#getSignatureElement',       'url' => '/api/{apiVersion}/account/signature/elements/{elementId}', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'account#patchSignatureElement',     'url' => '/api/{apiVersion}/account/signature/elements/{elementId}', 'verb' => 'PATCH', 'requirements' => $requirements],
		['name' => 'account#deleteSignatureElement',    'url' => '/api/{apiVersion}/account/signature/elements/{elementId}', 'verb' => 'DELETE', 'requirements' => $requirements],
	],
];
