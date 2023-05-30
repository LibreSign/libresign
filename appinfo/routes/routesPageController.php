<?php

$requirements = [
	'apiVersion' => 'v1',
];

return [
	'routes' => [
		// Pages - restricted
		['name' => 'page#index',                      'url' => '/', 'verb' => 'GET'],
		['name' => 'page#index',                      'url' => '/f/', 'verb' => 'GET'],
		['name' => 'page#index',                      'url' => '/f/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.+'], 'postfix' => 'front'],
		['name' => 'page#getPdfUser',                 'url' => '/pdf/user/{uuid}', 'verb' => 'GET'],
		['name' => 'page#resetPassword',              'url' => '/reset-password', 'verb' => 'GET'],
		// Pages - public
		['name' => 'page#sign',                       'url' => '/p/sign/{uuid}', 'verb' => 'GET'],
		['name' => 'page#sign',                       'url' => '/p/sign/{uuid}/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.+'], 'postfix' => 'extra'],
		['name' => 'page#signAccountFile',            'url' => '/p/account/files/approve/{uuid}', 'verb' => 'GET'],
		['name' => 'page#signAccountFile',            'url' => '/p/account/files/approve/{uuid}/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.+'], 'postfix' => 'extra'],
		['name' => 'page#validation',                 'url' => '/p/validation/{uuid}', 'verb' => 'GET'],
		['name' => 'page#validationFileWithShortUrl', 'url' => '/validation/{uuid}', 'verb' => 'GET'],
		['name' => 'page#validationFile',             'url' => '/p/validation/{uuid}', 'verb' => 'GET'],
		['name' => 'page#getPdf',                     'url' => '/p/pdf/{uuid}', 'verb' => 'GET']
	],
];
