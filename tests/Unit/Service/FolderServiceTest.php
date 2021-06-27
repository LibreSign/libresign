<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\FolderService;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;

final class FolderServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {

	public function testGetFolderWithInvalidNodeId() {
		$folder = $this->createMock(\OCP\Files\Folder::class);
		$root = $this->createMock(IRootFolder::class);
		$root
			->method('getUserFolder')
			->willReturn($folder);

		$config = $this->createMock(IConfig::class);
		$l10n = $this->createMock(IL10N::class);

		$service = new FolderService(
			$root,
			$config,
			$l10n,
			171
		);
		$this->expectErrorMessage('Invalid node');
		$service->getFolder(171);
	}

	public function testGetFolderWithValidNodeId() {
		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('getParent')
			->willReturn($node);
		$node->method('getById')
			->willReturn([$node]);
		$root = $this->createMock(IRootFolder::class);
		$root->method('getUserFolder')
			->willReturn($node);
		$config = $this->createMock(IConfig::class);
		$l10n = $this->createMock(IL10N::class);

		$service = new FolderService(
			$root,
			$config,
			$l10n,
			1
		);
		$actual = $service->getFolder(171);
		$this->assertInstanceOf(\OCP\Files\Folder::class, $actual);
	}
}
