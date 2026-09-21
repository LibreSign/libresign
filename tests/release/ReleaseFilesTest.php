<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

require_once __DIR__ . '/../../scripts/release/ReleaseFiles.php';

use LibreSign\Release\ReleaseFiles;
use PHPUnit\Framework\TestCase;

final class ReleaseFilesTest extends TestCase {
	private string $root;

	protected function setUp(): void {
		$this->root = sys_get_temp_dir() . '/libresign-release-' . bin2hex(random_bytes(6));
		mkdir($this->root . '/appinfo', 0777, true);
		mkdir($this->root . '/docs/changelogs', 0777, true);

		file_put_contents($this->root . '/appinfo/info.xml', "<info>\n  <version>15.0.0</version>\n</info>\n");
		file_put_contents($this->root . '/package.json', "{\n  \"name\": \"libresign\",\n  \"version\": \"15.0.0\"\n}\n");
		file_put_contents($this->root . '/package-lock.json', "{\n  \"name\": \"libresign\",\n  \"version\": \"15.0.0\",\n  \"lockfileVersion\": 3,\n  \"packages\": {\n    \"\": {\n      \"name\": \"libresign\",\n      \"version\": \"15.0.0\"\n    }\n  }\n}\n");
		file_put_contents($this->root . '/docs/changelogs/changelog-15.md', "## 15.0.0 - 2026-09-19\n\n### Fixed\n\n- old fix\n");
	}

	protected function tearDown(): void {
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);
		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}
		rmdir($this->root);
	}

	public function testApplyUpdatesAllVersionsAndPreservesJsonFormatting(): void {
		$files = new ReleaseFiles();
		$files->apply(
			$this->root,
			'15.0.1',
			"## 15.0.1 - 2026-09-20\n\n### Fixed\n\n- new fix\n",
		);

		$files->assertVersions($this->root, '15.0.1');
		$lock = file_get_contents($this->root . '/package-lock.json');

		self::assertSame(2, substr_count((string)$lock, '"version": "15.0.1"'));
		self::assertStringContainsString('  "name": "libresign"', (string)$lock);
		self::assertStringStartsWith('## 15.0.1 - 2026-09-20', file_get_contents($this->root . '/docs/changelogs/changelog-15.md'));
	}


	public function testChangelogPathUsesReleaseMajor(): void {
		$files = new ReleaseFiles();

		self::assertSame(
			$this->root . '/docs/changelogs/changelog-15.md',
			$files->changelogPath($this->root, '15.4.2'),
		);
	}

	public function testChangelogPathFailsWhenMajorFileIsMissing(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Missing changelog for LibreSign 16');

		(new ReleaseFiles())->changelogPath($this->root, '16.0.0');
	}

	public function testApplyRejectsDuplicateChangelogVersion(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('already contains');

		(new ReleaseFiles())->apply(
			$this->root,
			'15.0.0',
			"## 15.0.0 - 2026-09-19\n",
		);
	}
}
