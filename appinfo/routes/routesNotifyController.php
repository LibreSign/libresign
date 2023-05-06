<?php

$requirements = [
	'apiVersion' => 'v1',
];

return [
	'ocs' => [
		['name' => 'notify#signers', 'url' => '/api/{apiVersion}/notify/signers', 'verb' => 'POST', 'requirements' => $requirements],
	],
];
