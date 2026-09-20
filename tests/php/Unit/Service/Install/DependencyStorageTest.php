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
	): void {
		$storage = \OCP\Server::get(DependencyStorage::class);
		$target = InstallTarget::from($architecture, $distro);

		$folder = $storage->resourceFolder($target, $path);

		$this->assertSame($expectedFolderName, $folder->getName());
	}

	public static function resourceFolderProvider(): array {
		return [
			'architecture root' => ['x86_64', 'linux', '', 'x86_64'],
			'generic resource' => ['aarch64', 'linux', 'jsignpdf', 'jsignpdf'],
			'nested path' => ['x86_64', 'linux', 'test/folder1/folder2', 'folder2'],
			'java linux' => ['x86_64', 'linux', 'java', 'java'],
			'java alpine' => ['aarch64', 'alpine-linux', 'java', 'java'],
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
		$this->assertSame(
			'alpine-linux',
			$storage->resourceFolder($target, 'aarch64/alpine-linux')->getName(),
		);
	}
}
