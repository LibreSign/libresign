<?php

$requirements = [
	'apiVersion' => 'v1',
];

return [
	'ocs' => [
		['name' => 'admin#generateCertificate', 'url' => '/api/{apiVersion}/admin/certificate', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'admin#loadCertificate',     'url' => '/api/{apiVersion}/admin/certificate', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'admin#downloadBinaries',    'url' => '/api/{apiVersion}/admin/download-binaries', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'admin#downloadStatus',      'url' => '/api/{apiVersion}/admin/download-status', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'admin#configureCheck',      'url' => '/api/{apiVersion}/admin/configure-check', 'verb' => 'GET', 'requirements' => $requirements],
	],
];
