<?php

use Dredd\Hooks;
use OCP\IDBConnection;

require __DIR__ . '/../../../vendor/autoload.php';

require __DIR__ . '/../../bootstrap.php';

Hooks::beforeEach(function (&$transaction) {
	$db = \OC::$server->get(IDBConnection::class);
	$db->beginTransaction();
});

Hooks::afterEach(function (&$transaction) {
	$db = \OC::$server->get(IDBConnection::class);
	$db->rollBack();
});
