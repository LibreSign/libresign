<?php

use Symfony\Component\Yaml\Yaml;

require_once 'vendor/autoload.php';
$input = file_get_contents($argv[1]);
$array = Yaml::parse($input);
echo json_encode($array, JSON_UNESCAPED_SLASHES);