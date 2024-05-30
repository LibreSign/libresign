<?php

$requirements = [
	'apiVersion' => '(v1)',
];

return [
	'ocs' => [
		['name' => 'FileElement#post',   'url' => '/api/{apiVersion}/file-element/{uuid}', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'FileElement#patch',  'url' => '/api/{apiVersion}/file-element/{uuid}/{elementId}', 'verb' => 'PATCH', 'requirements' => $requirements],
		['name' => 'FileElement#delete', 'url' => '/api/{apiVersion}/file-element/{uuid}/{elementId}', 'verb' => 'DELETE', 'requirements' => $requirements],
	],
];
