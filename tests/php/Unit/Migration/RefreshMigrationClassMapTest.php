<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Migration;

use OCA\Libresign\Migration\RefreshMigrationClassMap;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

final class RefreshMigrationClassMapTest extends TestCase {
	private const MIGRATION_CLASS = 'OCA\\Libresign\\Migration\\Version99999Date20260713030303';
	private const MIGRATION_FILE = '/lib/Migration/Version99999Date20260713030303.php';

	public function testGetName(): void {
		$step = new RefreshMigrationClassMap();

		self::assertSame('Refresh LibreSign migration class map', $step->getName());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testRunRefreshesCurrentAppLoaderForNewMigrationFile(): void {
		$appRoot = dirname(__DIR__, 4);
		$migrationFile = $appRoot . self::MIGRATION_FILE;
		@unlink($migrationFile);

		try {
			clearstatcache(true, $migrationFile);
			self::assertFalse(class_exists(self::MIGRATION_CLASS));

			self::assertNotFalse(file_put_contents(
				$migrationFile,
				<<<'PHP'
				<?php

				declare(strict_types=1);

				namespace OCA\Libresign\Migration;

				final class Version99999Date20260713030303 {
				}
				PHP,
			));

			$step = new RefreshMigrationClassMap();
			$step->run($this->createMock(IOutput::class));

			self::assertTrue(class_exists(self::MIGRATION_CLASS));
		} finally {
			@unlink($migrationFile);
		}
	}
}
