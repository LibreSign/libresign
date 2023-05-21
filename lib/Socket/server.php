<?php

use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

require_once __DIR__ . '/../../vendor/autoload.php';

$socket = new SocketServer($argv[1]);

$socket->on('connection', function (ConnectionInterface $connection) {
	$connection->on('data', function ($data) use ($connection) {
		$connection->write($data);
		$connection->write("Data response\n");
	});
});

echo 'Listening on ' . $socket->getAddress() . PHP_EOL;
