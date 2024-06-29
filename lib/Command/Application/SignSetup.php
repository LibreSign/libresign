<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Command\Developer\SignSetup;
use Symfony\Component\Console\Application;

function exceptionHandler($exception) {
	echo "An unhandled exception has been thrown:" . PHP_EOL;
	echo $exception;
	exit(1);
}
try {
	require_once __DIR__ . '/../../../../../lib/base.php';

	if (!OC::$CLI) {
		echo "This script can be run from the command line only" . PHP_EOL;
		exit(1);
	}

	$config = \OCP\Server::get(\OCP\IConfig::class);
	set_exception_handler('exceptionHandler');

	$application = new Application();
	$signSetupCommand = \OC::$server->get(SignSetup::class);
	$application->add($signSetupCommand);
	$application->run();
} catch (Exception $ex) {
	exceptionHandler($ex);
} catch (Error $ex) {
	exceptionHandler($ex);
}
