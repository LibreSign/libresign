<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Only exercises the filesystem helpers, so it uses no Nextcloud test state.
 */
final class AppDataTestCaseTest extends PHPUnitTestCase {
	private string $root = '';

	public function setUp(): void {
		parent::setUp();
		$this->root = sys_get_temp_dir() . '/libresign-appdata-copy-' . bin2hex(random_bytes(6));
		mkdir($this->root . '/source', 0750, true);
		mkdir($this->root . '/dest', 0750, true);
	}

	public function tearDown(): void {
		self::callAppDataHelper('removeDirectoryRecursively', [$this->root]);
		parent::tearDown();
	}

	/**
	 * The source entry can stop being a directory while the copy runs, for
	 * example when another process removes it. The destination directory must
	 * keep a directory mode, or it can no longer be traversed or removed.
	 */
	public function testRecursiveCopyKeepsDirectoryModeWhenSourceEntryIsNotADirectory(): void {
		file_put_contents($this->root . '/source/entry', 'content');
		mkdir($this->root . '/dest/entry', 0750);

		self::callAppDataHelper('recursiveCopy', [
			$this->root . '/source',
			$this->root . '/dest',
		]);

		clearstatcache();
		self::assertSame(0750, fileperms($this->root . '/dest/entry') & 0777);
	}

	public function testRecursiveCopyCopiesFilesAndDirectoriesWithTestModes(): void {
		mkdir($this->root . '/source/directory', 0700);
		file_put_contents($this->root . '/source/directory/file', 'content');
		file_put_contents($this->root . '/source/script', 'content');
		chmod($this->root . '/source/script', 0755);

		self::callAppDataHelper('recursiveCopy', [
			$this->root . '/source',
			$this->root . '/dest',
		]);

		clearstatcache();
		self::assertSame('content', file_get_contents($this->root . '/dest/directory/file'));
		self::assertSame(0750, fileperms($this->root . '/dest/directory') & 0777);
		self::assertSame(0640, fileperms($this->root . '/dest/directory/file') & 0777);
		self::assertSame(0751, fileperms($this->root . '/dest/script') & 0777);
	}

	private static function callAppDataHelper(string $method, array $args): void {
		(new \ReflectionMethod(AppDataTestCase::class, $method))->invokeArgs(null, $args);
	}
}
