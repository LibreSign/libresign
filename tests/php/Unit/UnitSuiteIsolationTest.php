<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit;

final class UnitSuiteIsolationTest extends TestCase {
	public function testUnitTestsDoNotDependOnGlobalNextcloudState(): void {
		$forbidden = [
			'Server' . '::get(' => 'global Nextcloud service lookup',
			'OC' . '::$server' => 'global Nextcloud server container',
			'extends \\Test\\' . 'TestCase' => 'stateful Nextcloud test base',
			'@group ' . 'DB' => 'real database test group',
			'Group(' . "'DB'" . ')' => 'real database test attribute',
		];

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(__DIR__, \RecursiveDirectoryIterator::SKIP_DOTS),
		);

		foreach ($iterator as $file) {
			if (!$file->isFile() || $file->getExtension() !== 'php') {
				continue;
			}

			$content = file_get_contents($file->getPathname());
			self::assertIsString($content);

			foreach ($forbidden as $needle => $description) {
				self::assertStringNotContainsString(
					$needle,
					$content,
					sprintf('%s must not use %s', $file->getPathname(), $description),
				);
			}
		}
	}
}
