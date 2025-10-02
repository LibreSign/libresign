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
				$content = preg_replace('/"(_?twig_[a-z_0-9]+)([("])/', '"\\\\\\OCA\\\\\\Libresign\\\\\\Vendor\\\\\\\${1}${2}', $content);

				$content = preg_replace("/([^\\\\])(_?twig_[a-z_0-9]+)\(\"/", '${1}\\\\\\OCA\\\\\\Libresign\\\\\\Vendor\\\\\\\${2}("', $content);
				$content = preg_replace("/([^\\\\])(_?twig_[a-z_0-9]+)\('/", '${1}\\OCA\\Libresign\\Vendor\\\${2}(\'', $content);
			}

			return $content;
		},
		// patchers for Mpdf
		static function (string $filePath, string $prefix, string $content): string {
			if (!str_contains($filePath, 'mpdf/mpdf')) {
				return $content;
			}
			$searchReplacePairs = [
				'\\\\Mpdf\\\\' => '\\\\' . $prefix . '\\\\Mpdf\\\\',
				"'Mpdf\\\\" => "'" . $prefix . '\\\\Mpdf\\\\',
				"'\\\\Mpdf\\\\" => "'\\\\" . $prefix . '\\\\Mpdf\\\\',
				'@var \\\\Mpdf\\\\' => '@var \\\\' . $prefix . '\\\\Mpdf\\\\',
				'use Mpdf\\\\' => 'use ' . $prefix . '\\\\Mpdf\\\\',
				'namespace Mpdf\\\\' => 'namespace ' . $prefix . '\\\\Mpdf\\\\',
			];
			foreach ($searchReplacePairs as $search => $replace) {
				$content = str_replace($search, $replace, $content);
			}

			$file = basename($filePath);

			return match ($file) {
				'FpdiTrait.php' => str_replace('use \\setasign\\', "use \\$prefix\\setasign\\", $content),
				'Mpdf.php' => str_replace(["$prefix\\\\r\\\\n", "$prefix\\\\</t"], ['\\r\\n', '</t'], $content),
				'functions.php' => str_replace("namespace $prefix;", '', $content),
				'LoggerAwareInterface.php',
				'LoggerAwareTrait.php',
				'MpdfPsrLogAwareTrait.php',
				'PsrLogAwareTrait.php' => str_replace("\\$prefix\\Psr\\Log\\LoggerInterface", '\\Psr\\Log\\LoggerInterface', $content),
				default => $content,
			};
		},
		// patchers for phpseclib
		static function (string $filePath, string $prefix, string $content): string {
			if (!str_contains($filePath, 'phpseclib/phpseclib') || !str_ends_with($filePath, '.php')) {
				return $content;
			}
			$s_prefix = str_replace('\\', '\\\\', $prefix);
			$content = str_replace("'phpseclib3\\\\", "'\\\\" . $s_prefix . '\\\\phpseclib3\\\\', $content);
			$content = str_replace("'\\\\phpseclib3", "'\\\\" . $s_prefix . '\\\\phpseclib3', $content);
			return $content;
		},
		// patchers for pdfparser
		static function (string $filePath, string $prefix, string $content): string {
			if (!str_contains($filePath, 'smalot/pdfparser') || !str_ends_with($filePath, '.php')) {
				return $content;
			}
			$s_prefix = str_replace('\\', '\\\\', $prefix);
			$content = str_replace("'\\\\Smalot\\\\PdfParser", "'\\\\" . $s_prefix . '\\\\Smalot\\\\PdfParser', $content);
			return $content;
		},
	],
];
