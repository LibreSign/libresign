<?php

return [
	'routes' => [
		// API
		['name' => 'api#preflighted_cors', 'url' => '/api/0.1/{path}',
			'verb' => 'OPTIONS', 'requirements' => ['path' => '.+'], ],
		['name' => 'webhook#register', 'url' => '/api/0.1/webhook/register', 'verb' => 'POST'],
		['name' => 'libresign#sign', 'url' => '/api/0.1/sign', 'verb' => 'POST'],
		['name' => 'account#createToSign', 'url' => '/api/0.1/account/create/{uuid}', 'verb' => 'POST'],
		['name' => 'signature#generate', 'url' => '/api/0.1/signature/generate', 'verb' => 'POST'],
		['name' => 'signature#hasRootCert', 'url' => '/api/0.1/signature/has-root-cert', 'verb' => 'GET'],
		// Admin config
		['name' => 'admin#generateCertificate', 'url' => '/api/0.1/admin/certificate', 'verb' => 'POST'],
		['name' => 'admin#loadCertificate', 'url' => '/api/0.1/admin/certificate', 'verb' => 'GET'],

		// Pages
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'page#sign', 'url' => '/sign/{uuid}', 'verb' => 'GET']
	],
];
