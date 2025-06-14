<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\CertificateEngine;

use OCA\Libresign\Exception\LibresignException;

class CertificateHelper {
	public static function saveFile(string $filename, string $content): void {
		$success = file_put_contents($filename, $content);
		if ($success === false) {
			throw new LibresignException('Failure to save file. Check permission: ' . $filename);
		}
	}

	public static function arrayToIni(array $config) {
		$fileContent = '';
		foreach ($config as $i => $v) {
			if (is_array($v)) {
				$fileContent .= "\n[$i]\n" . self::arrayToIni($v);
			} else {
				if (is_bool($v)) {
					$v = (int)$v;
				}
				$fileContent .= "$i = " . (str_contains((string)$v, "\n") ? '"' . $v . '"' : $v) . "\n";
			}
		}
		return ltrim($fileContent, "\n");
	}
}
