<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Bootstrap;

use Composer\Autoload\ClassLoader;
use OCA\Libresign\Bootstrap\UpgradeSafeAutoloader;
use PHPUnit\Framework\TestCase;

final class UpgradeSafeAutoloaderTest extends TestCase {
	private const AUTOLOAD_COUNTER_KEY = 'libresign_upgrade_safe_autoloader_boot_count';
	private const MIGRATION_CLASS = 'OCA\\Libresign\\Migration\\Version99999Date20260713000000';
	private const NON_MIGRATION_CLASS = 'OCA\\Libresign\\Service\\GeneratedNonMigrationClass';
	private const MIGRATION_FILE = '/lib/Migration/Version99999Date20260713000000.php';

	/**
	 * @runInSeparateProcess
	 */
	public function testLoadsMigrationClassAfterComposerCachedPreviousMiss(): void {
		$appRoot = sys_get_temp_dir() . '/libresign-upgrade-autoload-' . uniqid('', true);
		$loader = new ClassLoader($appRoot . '/vendor');
		$loader->addPsr4('OCA\\Libresign\\', [$appRoot . '/lib'], true);
		$loader->register(true);

		try {
			mkdir($appRoot . '/lib/Migration', 0755, true);

			self::assertFalse(class_exists(self::MIGRATION_CLASS));

			file_put_contents(
				$appRoot . self::MIGRATION_FILE,
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
			$loader->unregister();
			self::removeDirectoryRecursively($appRoot);
		}
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testRequireOnceDoesNotReloadComposerAutoloadAfterMigrationFileAppears(): void {
		$appRoot = sys_get_temp_dir() . '/libresign-upgrade-autoload-' . uniqid('', true);
		unset($GLOBALS[self::AUTOLOAD_COUNTER_KEY]);

		try {
			mkdir($appRoot . '/composer', 0755, true);
			mkdir($appRoot . '/vendor', 0755, true);
			mkdir($appRoot . '/lib/Migration', 0755, true);

			file_put_contents(
				$appRoot . '/vendor/autoload.php',
				"<?php\n"
				. "declare(strict_types=1);\n"
				. 'if (!class_exists(\\Composer\\Autoload\\ClassLoader::class, false)) {' . "\n"
				. '    require_once ' . var_export(__DIR__ . '/../../../../vendor/composer/ClassLoader.php', true) . ';' . "\n"
				. "}\n"
				. "static \$loader = null;\n"
				. "if (\$loader === null) {\n"
				. "    \$loader = new \\Composer\\Autoload\\ClassLoader(__DIR__);\n"
				. "    \$loader->addPsr4('OCA\\\\Libresign\\\\', [dirname(__DIR__) . '/lib'], true);\n"
				. "    \$loader->register(true);\n"
				. "}\n"
				. "return \$loader;\n",
			);

			file_put_contents(
				$appRoot . '/composer/autoload.php',
				"<?php\n"
				. "declare(strict_types=1);\n"
				. "\$GLOBALS['" . self::AUTOLOAD_COUNTER_KEY . "'] = (\$GLOBALS['" . self::AUTOLOAD_COUNTER_KEY . "'] ?? 0) + 1;\n"
				. "return require_once __DIR__ . '/../vendor/autoload.php';\n",
			);

			$loader = require_once $appRoot . '/composer/autoload.php';
			self::assertInstanceOf(ClassLoader::class, $loader);
			self::assertSame(1, $GLOBALS[self::AUTOLOAD_COUNTER_KEY]);

			self::assertFalse(class_exists(self::MIGRATION_CLASS));

			file_put_contents(
				$appRoot . self::MIGRATION_FILE,
				<<<'PHP'
<?php

declare(strict_types=1);

namespace OCA\Libresign\Migration;

final class Version99999Date20260713000000 {
}
PHP,
			);

			require_once $appRoot . '/composer/autoload.php';
			self::assertSame(1, $GLOBALS[self::AUTOLOAD_COUNTER_KEY]);
			self::assertFalse(class_exists(self::MIGRATION_CLASS));

			UpgradeSafeAutoloader::registerCurrentAppLoader($appRoot);
			self::assertTrue(class_exists(self::MIGRATION_CLASS));
		} finally {
			$registeredLoader = ClassLoader::getRegisteredLoaders()[$appRoot . '/vendor'] ?? null;
			if ($registeredLoader instanceof ClassLoader) {
				$registeredLoader->unregister();
			}
			unset($GLOBALS[self::AUTOLOAD_COUNTER_KEY]);
			self::removeDirectoryRecursively($appRoot);
		}
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testFindsRegisteredLoaderForCurrentAppRoot(): void {
		$appRoot = sys_get_temp_dir() . '/libresign-upgrade-autoload-' . uniqid('', true);
		$loader = new ClassLoader($appRoot . '/vendor');
		$loader->addPsr4('OCA\\Libresign\\', [$appRoot . '/lib'], true);
		$loader->register(true);

		try {
			mkdir($appRoot . '/lib/Migration', 0755, true);

			self::assertFalse(class_exists(self::MIGRATION_CLASS));

			file_put_contents(
				$appRoot . self::MIGRATION_FILE,
				<<<'PHP'
<?php

declare(strict_types=1);

namespace OCA\Libresign\Migration;

final class Version99999Date20260713000000 {
}
PHP,
			);

			UpgradeSafeAutoloader::registerCurrentAppLoader($appRoot);

			self::assertTrue(class_exists(self::MIGRATION_CLASS));
		} finally {
			$loader->unregister();
			self::removeDirectoryRecursively($appRoot);
		}
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testRegistersOnlyMigrationClasses(): void {
		$appRoot = sys_get_temp_dir() . '/libresign-upgrade-autoload-' . uniqid('', true);
		$loader = new ClassLoader($appRoot . '/vendor');
		$loader->register(true);

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
			$loader->unregister();
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
