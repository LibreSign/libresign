<?php

namespace OCA\Libresign\Tests\Unit\Service;

use Exception;
use OCA\Libresign\Service\FolderService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\Folder;
use OCP\Files\IAppData;
use OCP\Files\IRootFolder;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IGroupManager;
use OCP\IL10N;

final class FakeFolder implements ISimpleFolder {
	public Folder $folder;

	public function getName(): string {
		return 'fake';
	}

	public function getDirectoryListing(): array {
		return [];
	}

	public function delete(): void {
	}

	public function fileExists(string $name): bool {
		return false;
	}

	public function getFile(string $name): ISimpleFile {
		throw new Exception('fake class');
	}

	public function newFile(string $name, $content = null): ISimpleFile {
		throw new Exception('fake class');
	}

	public function getFolder(string $name): ISimpleFolder {
		throw new Exception('fake class');
	}

	public function newFolder(string $path): ISimpleFolder {
		throw new Exception('fake class');
	}
}

final class FolderServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	public function testGetFolderWithInvalidNodeId() {
		$folder = $this->createMock(\OCP\Files\Folder::class);
		$root = $this->createMock(IRootFolder::class);
		$root
			->method('getUserFolder')
			->willReturn($folder);
		$userMountCache = $this->createMock(IUserMountCache::class);
		$userMountCache
			->method('getMountsForFileId')
			->willreturn([]);
		$appDataFactory = $this->createMock(IAppDataFactory::class);
		$groupManager = $this->createMock(IGroupManager::class);
		$appConfig = $this->createMock(IAppConfig::class);
		$l10n = $this->createMock(IL10N::class);

		$service = new FolderService(
			$root,
			$userMountCache,
			$appDataFactory,
			$groupManager,
			$appConfig,
			$l10n,
			171
		);
		$this->expectExceptionMessage('Invalid node');
		$service->getFolder(171);
	}

	public function testGetFolderWithValidNodeId() {
		$userMountCache = $this->createMock(IUserMountCache::class);
		$userMountCache
			->method('getMountsForFileId')
			->willreturn([]);
		$node = $this->createMock(\OCP\Files\File::class);
		$folder = $this->createMock(\OCP\Files\Folder::class);
		$node->method('getParent')
			->willReturn($folder);
		$root = $this->createMock(IRootFolder::class);
		$root->method('getUserFolder')
			->willReturn($node);

		$folder->method('nodeExists')->willReturn(true);
		$folder->method('get')->willReturn($folder);
		$fakeFolder = new FakeFolder();
		$fakeFolder->folder = $folder;
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')
			->willReturn($fakeFolder);
		$appDataFactory = $this->createMock(IAppDataFactory::class);
		$appDataFactory->method('get')
			->willReturn($appData);
		$groupManager = $this->createMock(IGroupManager::class);
		$appConfig = $this->createMock(IAppConfig::class);
		$l10n = $this->createMock(IL10N::class);

		$service = new FolderService(
			$root,
			$userMountCache,
			$appDataFactory,
			$groupManager,
			$appConfig,
			$l10n,
			1
		);
		$actual = $service->getFolder(171);
		$this->assertInstanceOf(\OCP\Files\Folder::class, $actual);
	}
}
