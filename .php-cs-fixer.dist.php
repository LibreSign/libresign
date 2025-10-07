<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require_once './vendor-bin/coding-standard/vendor/autoload.php';

use Nextcloud\CodingStandard\Config;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$config = new Config();
$config
	->setParallelConfig(ParallelConfigFactory::detect())
	->getFinder()
	->ignoreVCSIgnored(true)
	->notPath('build')
	->notPath('l10n')
	->notPath('src')
	->notPath('vendor')
	->notPath('vendor-bin')
	->notPath('3rdparty')
	->in(__DIR__);
return $config;
