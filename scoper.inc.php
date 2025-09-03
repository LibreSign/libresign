<?php

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

// based on Arthur Schiwon blogpost:
// https://arthur-schiwon.de/isolating-nextcloud-app-dependencies-php-scoper

return [
	'prefix' => 'OCA\\Libresign\\Vendor',
	'output-dir' => 'lib/Vendor',
	'finders' => [
		Finder::create()->files()
			->exclude([
				'vendor-bin'
			])
			->in('vendor/mikehaertl'),
	],
];
