<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Bootstrap;

use Composer\Autoload\ClassLoader;

final class UpgradeSafeAutoloader {
	private const MIGRATION_NAMESPACE = 'OCA\\Libresign\\Migration\\';
	private const MIGRATION_GLOB = '/lib/Migration/Version*.php';

	/**
	 * @var array<string, true>
	 */
	private static array $registeredRoots = [];

	private function __construct() {
	}

	public static function register(ClassLoader $loader, string $appRoot): void {
		$normalizedRoot = rtrim($appRoot, DIRECTORY_SEPARATOR);
		if ($normalizedRoot === '' || isset(self::$registeredRoots[$normalizedRoot])) {
			return;
		}

		$classMap = self::buildMigrationClassMap($normalizedRoot);
		if ($classMap !== []) {
			$loader->addClassMap($classMap);
		}

		self::$registeredRoots[$normalizedRoot] = true;
	}

	/**
	 * @return array<string, string>
	 */
	private static function buildMigrationClassMap(string $appRoot): array {
		$migrationFiles = glob($appRoot . self::MIGRATION_GLOB);
		if (!is_array($migrationFiles)) {
			return [];
		}

		$classMap = [];
		foreach ($migrationFiles as $migrationFile) {
			$classMap[self::MIGRATION_NAMESPACE . basename($migrationFile, '.php')] = $migrationFile;
		}

		return $classMap;
	}
}
