<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Migration;

use Composer\Autoload\ClassLoader;
use OCA\Libresign\Migration\UpgradeSafeAutoloader;
use PHPUnit\Framework\TestCase;

final class UpgradeSafeAutoloaderTest extends TestCase {
	private const AUTOLOAD_COUNTER_KEY = 'libresign_upgrade_safe_autoloader_boot_count';
	private const HELPER_CLASS = 'OCA\\Libresign\\Service\\FreshHelper';
	private const HELPER_FILE = '/lib/Service/FreshHelper.php';
	private const MIGRATION_CLASS = 'OCA\\Libresign\\Migration\\Version99999Date20260713000000';
	private const MIGRATION_FILE = '/lib/Migration/Version99999Date20260713000000.php';
	private const PROBE_FUNCTION = 'libresign_upgrade_safe_probe_function';
	private const PROBE_FUNCTION_FILE = '/support/runtime-functions.php';
	private const VENDOR_CLASS = 'OCA\\Libresign\\Vendor\\Demo\\Widget';
	private const VENDOR_FILE = '/vendor-prefixed/Demo/Widget.php';

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
			self::writeComposerMetadata($appRoot);

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

			$registeredLoader = ClassLoader::getRegisteredLoaders()[$appRoot . '/vendor'] ?? null;
			self::assertInstanceOf(ClassLoader::class, $registeredLoader);
			self::assertNotSame($loader, $registeredLoader);
			self::assertTrue(class_exists(self::MIGRATION_CLASS));
		} finally {
			self::unregisterLoaderForAppRoot($appRoot);
			self::removeDirectoryRecursively($appRoot);
		}
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testRequireOnceDoesNotReloadComposerAutoloadAfterMigrationFileAppears(): void {
		$appRoot = sys_get_temp_dir() . '/libresign-upgrade-autoload-' . uniqid('', true);
		$loader = null;
		unset($GLOBALS[self::AUTOLOAD_COUNTER_KEY]);

		try {
			mkdir($appRoot . '/composer', 0755, true);
			mkdir($appRoot . '/vendor', 0755, true);
			mkdir($appRoot . '/lib/Migration', 0755, true);
			mkdir($appRoot . '/lib/Service', 0755, true);
			mkdir($appRoot . '/vendor-prefixed/Demo', 0755, true);
			self::writeComposerMetadata($appRoot);

			file_put_contents(
				$appRoot . '/vendor/autoload.php',
				"<?php\n"
				. "declare(strict_types=1);\n"
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
			self::assertFalse(class_exists(self::HELPER_CLASS));
			self::assertFalse(class_exists(self::VENDOR_CLASS));

			file_put_contents(
				$appRoot . self::MIGRATION_FILE,
				<<<'PHP'
<?php

declare(strict_types=1);

namespace OCA\Libresign\Migration;

use OCA\Libresign\Service\FreshHelper;
use OCA\Libresign\Vendor\Demo\Widget;

final class Version99999Date20260713000000 {
	public static function run(): string {
		return FreshHelper::ping() . '-' . Widget::name();
	}
}
PHP,
			);

			file_put_contents(
				$appRoot . self::HELPER_FILE,
				<<<'PHP'
<?php

declare(strict_types=1);

namespace OCA\Libresign\Service;

final class FreshHelper {
	public static function ping(): string {
		return 'pong';
	}
}
PHP,
			);

			file_put_contents(
				$appRoot . self::VENDOR_FILE,
				<<<'PHP'
<?php

declare(strict_types=1);

namespace OCA\Libresign\Vendor\Demo;

final class Widget {
	public static function name(): string {
		return 'widget';
	}
}
PHP,
			);

			self::writeComposerMetadata($appRoot, [
				self::VENDOR_CLASS => $appRoot . self::VENDOR_FILE,
			]);

			require_once $appRoot . '/composer/autoload.php';
			self::assertSame(1, $GLOBALS[self::AUTOLOAD_COUNTER_KEY]);
			self::assertFalse(class_exists(self::MIGRATION_CLASS));
			self::assertFalse(class_exists(self::HELPER_CLASS));
			self::assertFalse(class_exists(self::VENDOR_CLASS));

			UpgradeSafeAutoloader::registerCurrentAppLoader($appRoot);
			$registeredLoader = ClassLoader::getRegisteredLoaders()[$appRoot . '/vendor'] ?? null;
			self::assertInstanceOf(ClassLoader::class, $registeredLoader);
			self::assertNotSame($loader, $registeredLoader);
			self::assertTrue(class_exists(self::MIGRATION_CLASS));
			self::assertTrue(class_exists(self::HELPER_CLASS));
			self::assertTrue(class_exists(self::VENDOR_CLASS));
			$migrationClass = self::MIGRATION_CLASS;
			self::assertSame('pong-widget', $migrationClass::run());
		} finally {
			self::unregisterLoaderForAppRoot($appRoot);
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
			self::writeComposerMetadata($appRoot);

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

			$registeredLoader = ClassLoader::getRegisteredLoaders()[$appRoot . '/vendor'] ?? null;
			self::assertInstanceOf(ClassLoader::class, $registeredLoader);
			self::assertNotSame($loader, $registeredLoader);
			self::assertTrue(class_exists(self::MIGRATION_CLASS));
		} finally {
			self::unregisterLoaderForAppRoot($appRoot);
			self::removeDirectoryRecursively($appRoot);
		}
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testLoadsVendorClassFromFreshComposerLoader(): void {
		$appRoot = sys_get_temp_dir() . '/libresign-upgrade-autoload-' . uniqid('', true);
		$loader = new ClassLoader($appRoot . '/vendor');
		$loader->addPsr4('OCA\\Libresign\\', [$appRoot . '/lib'], true);
		$loader->register(true);

		try {
			mkdir($appRoot . '/vendor-prefixed/Demo', 0755, true);

			file_put_contents(
				$appRoot . self::VENDOR_FILE,
				<<<'PHP'
<?php

declare(strict_types=1);

namespace OCA\Libresign\Vendor\Demo;

final class Widget {
	public static function name(): string {
		return 'widget';
	}
}
PHP,
			);

			self::writeComposerMetadata($appRoot, [
				self::VENDOR_CLASS => $appRoot . self::VENDOR_FILE,
			]);

			self::assertFalse(class_exists(self::VENDOR_CLASS));

			UpgradeSafeAutoloader::register($loader, $appRoot);

			$registeredLoader = ClassLoader::getRegisteredLoaders()[$appRoot . '/vendor'] ?? null;
			self::assertInstanceOf(ClassLoader::class, $registeredLoader);
			self::assertNotSame($loader, $registeredLoader);
			self::assertTrue(class_exists(self::VENDOR_CLASS));
			$vendorClass = self::VENDOR_CLASS;
			self::assertSame('widget', $vendorClass::name());
		} finally {
			self::unregisterLoaderForAppRoot($appRoot);
			self::removeDirectoryRecursively($appRoot);
		}
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testLoadsComposerFilesFromFreshLoader(): void {
		$appRoot = sys_get_temp_dir() . '/libresign-upgrade-autoload-' . uniqid('', true);
		$loader = new ClassLoader($appRoot . '/vendor');
		$loader->register(true);

		try {
			mkdir(dirname($appRoot . self::PROBE_FUNCTION_FILE), 0755, true);

			file_put_contents(
				$appRoot . self::PROBE_FUNCTION_FILE,
				<<<'PHP'
<?php

declare(strict_types=1);

function libresign_upgrade_safe_probe_function(): string {
	return 'ok';
}
PHP,
			);

			self::writeComposerMetadata($appRoot, [], [
				'probe-function' => $appRoot . self::PROBE_FUNCTION_FILE,
			]);

			self::assertFalse(function_exists(self::PROBE_FUNCTION));

			UpgradeSafeAutoloader::register($loader, $appRoot);

			self::assertTrue(function_exists(self::PROBE_FUNCTION));
			$function = self::PROBE_FUNCTION;
			self::assertSame('ok', $function());
		} finally {
			self::unregisterLoaderForAppRoot($appRoot);
			self::removeDirectoryRecursively($appRoot);
		}
	}

	private static function writeComposerMetadata(string $appRoot, array $classMap = [], array $files = []): void {
		$directory = $appRoot . '/vendor/composer';
		if (!is_dir($directory)) {
			mkdir($directory, 0755, true);
		}

		file_put_contents(
			$directory . '/autoload_namespaces.php',
			"<?php\n\nreturn [];\n",
		);
		file_put_contents(
			$directory . '/autoload_psr4.php',
			"<?php\n\nreturn " . var_export([
				'OCA\\Libresign\\' => [$appRoot . '/lib'],
			], true) . ";\n",
		);
		file_put_contents(
			$directory . '/autoload_classmap.php',
			"<?php\n\nreturn " . var_export($classMap, true) . ";\n",
		);
		file_put_contents(
			$directory . '/autoload_files.php',
			"<?php\n\nreturn " . var_export($files, true) . ";\n",
		);
	}

	private static function unregisterLoaderForAppRoot(string $appRoot): void {
		$registeredLoader = ClassLoader::getRegisteredLoaders()[$appRoot . '/vendor'] ?? null;
		if ($registeredLoader instanceof ClassLoader) {
			$registeredLoader->unregister();
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
