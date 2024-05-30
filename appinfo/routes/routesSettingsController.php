<?php

$requirements = [
	'apiVersion' => '(v1)',
];

return [
	'ocs' => [
		['name' => 'setting#hasRootCert', 'url' => '/api/{apiVersion}/setting/has-root-cert', 'verb' => 'GET', 'requirements' => $requirements],
	],
];
