<?php

$requirements = [
	'apiVersion' => 'v1',
];

return [
	'ocs' => [
		['name' => 'notify#signer', 'url' => '/api/{apiVersion}/notify/signer', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'notify#signers', 'url' => '/api/{apiVersion}/notify/signers', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'notify#notificationDismiss', 'url' => '/api/{apiVersion}/notif/notification', 'verb' => 'DELETE', 'requirements' => $requirements],
	],
];
