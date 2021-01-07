<?php

require_once __DIR__.'/../../../lib/base.php';
require_once __DIR__.'/../vendor/autoload.php';

\OC::$loader->addValidRoot(OC::$SERVERROOT . '/tests');
\OC_App::loadApp('signer');