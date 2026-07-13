<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Bootstrap;

final class UpgradeSafeAutoloader {
	private const APP_NAMESPACE = 'OCA\\Libresign\\';
	private const EXCLUDED_PREFIXES = [
		self::APP_NAMESPACE . '3rdparty\\',
		self::APP_NAMESPACE . 'Tests\\',
		self::APP_NAMESPACE . 'Vendor\\',
	];

	/**
	 * @var array<string, true>
	 */
	private static array $registeredRoots = [];

	private function __construct() {
	}

	public static function register(string $appRoot): void {
		$normalizedRoot = rtrim($appRoot, DIRECTORY_SEPARATOR);
		if ($normalizedRoot === '' || isset(self::$registeredRoots[$normalizedRoot])) {
			return;
		}

		spl_autoload_register(
			static function (string $class) use ($normalizedRoot): void {
				$file = self::resolveFile($normalizedRoot, $class);
				if ($file !== null) {
					require_once $file;
				}
			},
			true,
			true,
		);

		self::$registeredRoots[$normalizedRoot] = true;
	}

	private static function resolveFile(string $appRoot, string $class): ?string {
		if (!str_starts_with($class, self::APP_NAMESPACE) || self::isExcludedNamespace($class)) {
			return null;
		}

		$relativeClass = substr($class, strlen(self::APP_NAMESPACE));
		$path = $appRoot . '/lib/' . str_replace('\\', '/', $relativeClass) . '.php';

		if (!is_file($path)) {
			return null;
		}

		return $path;
	}

	private static function isExcludedNamespace(string $class): bool {
		foreach (self::EXCLUDED_PREFIXES as $prefix) {
			if (str_starts_with($class, $prefix)) {
				return true;
			}
		}

		return false;
	}
}
