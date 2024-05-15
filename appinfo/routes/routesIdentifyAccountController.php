<?php

$requirements = [
	'apiVersion' => '(v1)',
];

return [
	'ocs' => [
		['name' => 'IdentifyAccount#search', 'url' => '/api/{apiVersion}/identify-account/search', 'verb' => 'GET', 'requirements' => $requirements],
	],
];
