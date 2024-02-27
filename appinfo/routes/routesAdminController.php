<?php

$requirements = [
	'apiVersion' => 'v1',
];

return [
	'ocs' => [
		['name' => 'admin#installAndValidate',         'url' => '/api/{apiVersion}/admin/install-and-validate', 'verb' => 'GET'],
		['name' => 'admin#generateCertificateCfssl',   'url' => '/api/{apiVersion}/admin/certificate/cfssl', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'admin#generateCertificateOpenSsl', 'url' => '/api/{apiVersion}/admin/certificate/openssl', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'admin#loadCertificate',            'url' => '/api/{apiVersion}/admin/certificate', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'admin#configureCheck',             'url' => '/api/{apiVersion}/admin/configure-check', 'verb' => 'GET', 'requirements' => $requirements],
	],
];
