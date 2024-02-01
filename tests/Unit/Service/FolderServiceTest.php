<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\FolderService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCP\IL10N;

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
		$appConfig = $this->createMock(IAppConfig::class);
		$l10n = $this->createMock(IL10N::class);

		$service = new FolderService(
			$root,
			$userMountCache,
			$appDataFactory,
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
		$root->method('getById')
			->willReturn([$node]);
		$appDataFactory = $this->createMock(IAppDataFactory::class);
		$appConfig = $this->createMock(IAppConfig::class);
		$l10n = $this->createMock(IL10N::class);

		$service = new FolderService(
			$root,
			$userMountCache,
			$appDataFactory,
			$appConfig,
			$l10n,
			1
		);
		$actual = $service->getFolder(171);
		$this->assertInstanceOf(\OCP\Files\Folder::class, $actual);
	}
}
