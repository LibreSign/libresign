<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Install;

use OCA\Libresign\Service\Install\DependencyStorage;
use OCA\Libresign\Service\Install\InstallTarget;
use PHPUnit\Framework\Attributes\DataProvider;

final class DependencyStorageTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/**
	 * @runInSeparateProcess
	 */
	#[DataProvider('resourceFolderProvider')]
	public function testResourceFolderUsesTarget(
		string $architecture,
		string $distro,
		string $path,
		string $expectedFolderName,
		string $expectedPathSuffix,
	): void {
		$storage = \OCP\Server::get(DependencyStorage::class);
		$target = InstallTarget::from($architecture, $distro);

		$folder = $storage->resourceFolder($target, $path);

		$this->assertSame($expectedFolderName, $folder->getName());
		$this->assertStringEndsWith($expectedPathSuffix, $storage->pathOfFolder($folder));
	}

	public static function resourceFolderProvider(): array {
		return [
			'x86 root' => ['x86_64', 'linux', '', 'x86_64', '/libresign/x86_64'],
			'arm root' => ['aarch64', 'linux', '', 'aarch64', '/libresign/aarch64'],
			'x86 generic resource' => ['x86_64', 'linux', 'jsignpdf', 'jsignpdf', '/libresign/x86_64/jsignpdf'],
			'arm generic resource' => ['aarch64', 'linux', 'jsignpdf', 'jsignpdf', '/libresign/aarch64/jsignpdf'],
			'x86 nested path' => ['x86_64', 'linux', 'test/folder1/folder2', 'folder2', '/libresign/x86_64/test/folder1/folder2'],
			'arm nested path' => ['aarch64', 'linux', 'test/folder1/folder2', 'folder2', '/libresign/aarch64/test/folder1/folder2'],
			'x86 java linux' => ['x86_64', 'linux', 'java', 'java', '/libresign/x86_64/linux/java'],
			'x86 java alpine' => ['x86_64', 'alpine-linux', 'java', 'java', '/libresign/x86_64/alpine-linux/java'],
			'arm java linux' => ['aarch64', 'linux', 'java', 'java', '/libresign/aarch64/linux/java'],
			'arm java alpine' => ['aarch64', 'alpine-linux', 'java', 'java', '/libresign/aarch64/alpine-linux/java'],
		];
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testEmptyResourceFolderReplacesStaleContents(): void {
		$storage = \OCP\Server::get(DependencyStorage::class);
		$target = InstallTarget::from('x86_64', 'linux');
		$folder = $storage->resourceFolder($target, 'installer-stale-test');
		$folder->newFile('old-file', 'old');

		$cleanFolder = $storage->resourceFolder(
			$target,
			'installer-stale-test',
			empty: true,
		);

		$this->assertSame([], $cleanFolder->getDirectoryListing());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testEmptyJavaFolderKeepsArchitectureAndDistroParents(): void {
		$storage = \OCP\Server::get(DependencyStorage::class);
		$target = InstallTarget::from('aarch64', 'alpine-linux');
		$folder = $storage->resourceFolder($target, 'java');
		$folder->newFile('old-java', 'old');

		$cleanFolder = $storage->resourceFolder($target, 'java', empty: true);

		$this->assertSame('java', $cleanFolder->getName());
		$this->assertSame([], $cleanFolder->getDirectoryListing());
	}
}
