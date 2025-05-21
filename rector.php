<?php

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Nextcloud\Rector\Set\NextcloudSets;
use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;

return RectorConfig::configure()
	->withPaths([
		__DIR__ . '/appinfo',
		__DIR__ . '/composer',
		__DIR__ . '/lib',
		__DIR__ . '/templates',
		__DIR__ . '/tests/php',
	])
	->withPhpSets(php81: true)
	->withSets([
		NextcloudSets::NEXTCLOUD_30,
	])
	->withTypeCoverageLevel(0)
	->withSkip([
		ReadOnlyPropertyRector::class,
	]);
;
