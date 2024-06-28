<?php

$requirements = [
	'apiVersion' => '{apiVersion}',
	'path' => '.+',
];

return [
	'routes' => [
		// API
		['name' => 'api#preflighted_cors',            'url' => '/api/{apiVersion}/{path}', 'verb' => 'OPTIONS', 'requirements' => $requirements],
	],
];
