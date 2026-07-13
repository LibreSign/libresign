<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Bootstrap;

use OCA\Libresign\Bootstrap\UpgradeSafeAutoloader;
use PHPUnit\Framework\TestCase;

final class UpgradeSafeAutoloaderTest extends TestCase {
	private const MIGRATION_CLASS = 'OCA\\Libresign\\Migration\\Version99999Date20260713000000';
	private const NON_MIGRATION_CLASS = 'OCA\\Libresign\\Service\\GeneratedNonMigrationClass';

	/**
	 * @runInSeparateProcess
	 */
	public function testLoadsMigrationClassAfterComposerCachedPreviousMiss(): void {
		$appRoot = sys_get_temp_dir() . '/libresign-upgrade-autoload-' . uniqid('', true);
		$loader = require __DIR__ . '/../../../../vendor/autoload.php';

		try {
			mkdir($appRoot . '/lib/Migration', 0755, true);

			self::assertFalse(class_exists(self::MIGRATION_CLASS));

			file_put_contents(
				$appRoot . '/lib/Migration/Version99999Date20260713000000.php',
				<<<'PHP'
<?php

declare(strict_types=1);

namespace OCA\Libresign\Migration;

final class Version99999Date20260713000000 {
}
PHP,
			);

			UpgradeSafeAutoloader::register($loader, $appRoot);

			self::assertTrue(class_exists(self::MIGRATION_CLASS));
		} finally {
			self::removeDirectoryRecursively($appRoot);
		}
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testRegistersOnlyMigrationClasses(): void {
		$appRoot = sys_get_temp_dir() . '/libresign-upgrade-autoload-' . uniqid('', true);
		$loader = require __DIR__ . '/../../../../vendor/autoload.php';

		try {
			mkdir($appRoot . '/lib/Service', 0755, true);

			file_put_contents(
				$appRoot . '/lib/Service/GeneratedNonMigrationClass.php',
				<<<'PHP'
<?php

declare(strict_types=1);

namespace OCA\Libresign\Service;

final class GeneratedNonMigrationClass {
}
PHP,
			);

			UpgradeSafeAutoloader::register($loader, $appRoot);

			self::assertFalse(class_exists(self::NON_MIGRATION_CLASS));
		} finally {
			self::removeDirectoryRecursively($appRoot);
		}
	}

	private static function removeDirectoryRecursively(string $path): void {
		if (!file_exists($path)) {
			return;
		}

		if (is_file($path)) {
			@unlink($path);
			return;
		}

		$entries = scandir($path);
		if (!is_array($entries)) {
			@rmdir($path);
			return;
		}

		foreach ($entries as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			self::removeDirectoryRecursively($path . '/' . $entry);
		}

		@rmdir($path);
	}
}
