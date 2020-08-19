<?php

if (false === (@include_once __DIR__.'/../lib/vendor/autoload.php')) {
    throw new Exception('Cannot include autoload. Did you run install dependencies using composer?');
}
$app = \OC::$server->query(OCA\Dsv\AppInfo\Application::class);
