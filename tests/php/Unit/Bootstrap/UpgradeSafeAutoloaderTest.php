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
	private const TEST_CLASS = 'OCA\\Libresign\\Tests\\Unit\\Bootstrap\\GeneratedExcludedClass';
	private const VENDOR_CLASS = 'OCA\\Libresign\\Vendor\\GeneratedExcludedClass';

	/**
	 * @runInSeparateProcess
	 */
	public function testLoadsMigrationClassAfterComposerCachedPreviousMiss(): void {
		$appRoot = sys_get_temp_dir() . '/libresign-upgrade-autoload-' . uniqid('', true);

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

			UpgradeSafeAutoloader::register($appRoot);

			self::assertTrue(class_exists(self::MIGRATION_CLASS));
		} finally {
			self::removeDirectoryRecursively($appRoot);
		}
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSkipsVendorAndTestsNamespaces(): void {
		$appRoot = sys_get_temp_dir() . '/libresign-upgrade-autoload-' . uniqid('', true);

		try {
			mkdir($appRoot . '/lib/Tests/Unit/Bootstrap', 0755, true);
			mkdir($appRoot . '/lib/Vendor', 0755, true);

			file_put_contents(
				$appRoot . '/lib/Tests/Unit/Bootstrap/GeneratedExcludedClass.php',
				<<<'PHP'
<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Bootstrap;

final class GeneratedExcludedClass {
}
PHP,
			);

			file_put_contents(
				$appRoot . '/lib/Vendor/GeneratedExcludedClass.php',
				<<<'PHP'
<?php

declare(strict_types=1);

namespace OCA\Libresign\Vendor;

final class GeneratedExcludedClass {
}
PHP,
			);

			UpgradeSafeAutoloader::register($appRoot);

			self::assertFalse(class_exists(self::TEST_CLASS));
			self::assertFalse(class_exists(self::VENDOR_CLASS));
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
