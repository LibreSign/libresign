<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Migration;

use OCA\Libresign\Migration\RefreshAutoloaderBeforeMigrations;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

final class RefreshAutoloaderBeforeMigrationsTest extends TestCase {
	private const HELPER_CLASS = 'OCA\\Libresign\\Service\\FreshRuntimeHelper';
	private const HELPER_FILE = '/lib/Service/FreshRuntimeHelper.php';
	private const MIGRATION_CLASS = 'OCA\\Libresign\\Migration\\Version99999Date20260713030303';
	private const MIGRATION_FILE = '/lib/Migration/Version99999Date20260713030303.php';

	public function testGetName(): void {
		$step = new RefreshAutoloaderBeforeMigrations();

		self::assertSame('Refresh LibreSign autoloader before migrations', $step->getName());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testRunRefreshesCurrentAppLoaderForNewMigrationFile(): void {
		$appRoot = dirname(__DIR__, 4);
		$helperFile = $appRoot . self::HELPER_FILE;
		$migrationFile = $appRoot . self::MIGRATION_FILE;
		@unlink($helperFile);
		@unlink($migrationFile);

		try {
			clearstatcache(true, $helperFile);
			clearstatcache(true, $migrationFile);
			self::assertFalse(class_exists(self::MIGRATION_CLASS));
			self::assertFalse(class_exists(self::HELPER_CLASS));

			self::assertNotFalse(file_put_contents(
				$helperFile,
				<<<'PHP'
				<?php

				declare(strict_types=1);

				namespace OCA\Libresign\Service;

				final class FreshRuntimeHelper {
					public static function ping(): string {
						return 'pong';
					}
				}
				PHP,
			));

			self::assertNotFalse(file_put_contents(
				$migrationFile,
				<<<'PHP'
				<?php

				declare(strict_types=1);

				namespace OCA\Libresign\Migration;

				use OCA\Libresign\Service\FreshRuntimeHelper;

				final class Version99999Date20260713030303 {
					public static function run(): string {
						return FreshRuntimeHelper::ping();
					}
				}
				PHP,
			));

			$step = new RefreshAutoloaderBeforeMigrations();
			$step->run($this->createMock(IOutput::class));

			self::assertTrue(class_exists(self::MIGRATION_CLASS));
			self::assertTrue(class_exists(self::HELPER_CLASS));
			$migrationClass = self::MIGRATION_CLASS;
			self::assertSame('pong', $migrationClass::run());
		} finally {
			@unlink($helperFile);
			@unlink($migrationFile);
		}
	}
}
