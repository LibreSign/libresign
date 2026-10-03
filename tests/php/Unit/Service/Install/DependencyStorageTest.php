<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Install;

use OCA\Libresign\Service\Install\DependencyStorage;
use OCA\Libresign\Service\Install\InstallTarget;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\DataProvider;

final class InMemoryDependencyFolder implements ISimpleFolder {
	public object $folder;

	/** @var array<string, self> */
	private array $folders = [];

	public function __construct(
		private string $name,
		string $internalPath,
	) {
		$this->folder = new class($internalPath) {
			public function __construct(private string $internalPath) {
			}

			public function getInternalPath(): string {
				return $this->internalPath;
			}
		};
	}

	public function getDirectoryListing(): array {
		return array_values($this->folders);
	}

	public function fileExists(string $name): bool {
		return false;
	}

	public function getFile(string $name): ISimpleFile {
		throw new NotFoundException();
	}

	public function newFile(string $name, $content = null): ISimpleFile {
		throw new \LogicException('Files are not required by this test double.');
	}

	public function delete(): void {
		$this->folders = [];
	}

	public function getName(): string {
		return $this->name;
	}

	public function getFolder(string $name): ISimpleFolder {
		if (!isset($this->folders[$name])) {
			throw new NotFoundException();
		}
		return $this->folders[$name];
	}

	public function newFolder(string $path): ISimpleFolder {
		$name = basename($path);
		$internalPath = trim($this->folder->getInternalPath() . '/' . $path, '/');
		return $this->folders[$name] = new self($name, $internalPath);
	}

	public function getOrCreateFolder(string $path, int $maxRetries = 5): ISimpleFolder {
		$folder = $this;
		foreach (array_filter(explode('/', $path), 'strlen') as $part) {
			try {
				$folder = $folder->getFolder($part);
			} catch (NotFoundException) {
				$folder = $folder->newFolder($part);
			}
		}
		return $folder;
	}
}

final class DependencyStorageTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private function getStorage(): DependencyStorage {
		$root = new InMemoryDependencyFolder('libresign', 'libresign');
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->with('/')->willReturn($root);

		$appDataFactory = $this->createMock(IAppDataFactory::class);
		$appDataFactory->method('get')->with('libresign')->willReturn($appData);

		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValue')->willReturn('');

		return new DependencyStorage($appDataFactory, $config);
	}

	#[DataProvider('resourceFolderProvider')]
	public function testResourceFolderUsesTarget(
		string $architecture,
		string $distro,
		string $path,
		string $expectedFolderName,
		string $expectedPathSuffix,
	): void {
		$storage = $this->getStorage();
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

	public function testEmptyResourceFolderReplacesStaleContents(): void {
		$storage = $this->getStorage();
		$target = InstallTarget::from('x86_64', 'linux');
		$folder = $storage->resourceFolder($target, 'installer-stale-test');
		$folder->newFolder('old-folder');

		$cleanFolder = $storage->resourceFolder(
			$target,
			'installer-stale-test',
			empty: true,
		);

		$this->assertSame([], $cleanFolder->getDirectoryListing());
	}

	public function testEmptyJavaFolderKeepsArchitectureAndDistroParents(): void {
		$storage = $this->getStorage();
		$target = InstallTarget::from('aarch64', 'alpine-linux');
		$folder = $storage->resourceFolder($target, 'java');
		$folder->newFolder('old-folder');

		$cleanFolder = $storage->resourceFolder($target, 'java', empty: true);

		$this->assertSame('java', $cleanFolder->getName());
		$this->assertSame([], $cleanFolder->getDirectoryListing());
	}
}
