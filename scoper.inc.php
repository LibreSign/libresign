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
				'bamarni',
				'bin',
				'composer',
				'nextcloud',
			])
			->notName('autoload.php')
			->in('vendor'),
	],
	'patchers' => [
		// patchers for twig
		static function (string $filePath, string $prefix, string $content): string {
			// correct use statements in generated templates
			if (preg_match('%twig/src/Node/ModuleNode\\.php$%', $filePath)) {
				return str_replace('"use Twig\\', '"use ' . str_replace('\\', '\\\\', $prefix) . '\\\\Twig\\', $content);
			}

			// correctly scope function calls to twig_... globals (which will not be globals anymore) in strings
			if (strpos($filePath, 'twig/twig') !== false
				|| preg_match('/\\.php$/', $filePath)
			) {
				$content = preg_replace("/([^'\"])(_?twig_[a-z_0-9]+)\\(/", '${1}\\OCA\\Libresign\\Vendor\\\${2}(', $content);

				$content = preg_replace("/'(_?twig_[a-z_0-9]+)([('])/", '\'\\OCA\\Libresign\\vendor\\\${1}${2}', $content);
				$content = preg_replace("/\"(_?twig_[a-z_0-9]+)([(\"])/", '"\\\\\\OCA\\\\\\Libresign\\\\\\Vendor\\\\\\\${1}${2}', $content);

				$content = preg_replace("/([^\\\\])(_?twig_[a-z_0-9]+)\(\"/", '${1}\\\\\\OCA\\\\\\Libresign\\\\\\Vendor\\\\\\\${2}("', $content);
				$content = preg_replace("/([^\\\\])(_?twig_[a-z_0-9]+)\('/", '${1}\\OCA\\Libresign\\Vendor\\\${2}(\'', $content);
			}

			return $content;
		},
	],
];
