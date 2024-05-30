<?php

$requirements = [
	'apiVersion' => '(v1)',
];

return [
	'ocs' => [
		['name' => 'account#createToSign',              'url' => '/api/{apiVersion}/account/create/{uuid}', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'account#me',                        'url' => '/api/{apiVersion}/account/me', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'account#uploadPfx',                 'url' => '/api/{apiVersion}/account/pfx', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'account#readPfxData',               'url' => '/api/{apiVersion}/account/pfx/read', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'account#updatePfxPassword',         'url' => '/api/{apiVersion}/account/pfx', 'verb' => 'PATCH', 'requirements' => $requirements],
		['name' => 'account#deletePfx',                 'url' => '/api/{apiVersion}/account/pfx', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'account#updateSettings',            'url' => '/api/{apiVersion}/account/settings', 'verb' => 'PATCH', 'requirements' => $requirements],
		['name' => 'account#signatureGenerate',         'url' => '/api/{apiVersion}/account/signature', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'account#addFiles',                  'url' => '/api/{apiVersion}/account/files', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'account#deleteFile',                'url' => '/api/{apiVersion}/account/files', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'account#accountFileListToOwner',    'url' => '/api/{apiVersion}/account/files', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'account#accountFileListToApproval', 'url' => '/api/{apiVersion}/account/files/approval/list', 'verb' => 'GET', 'requirements' => $requirements],
	],
];
