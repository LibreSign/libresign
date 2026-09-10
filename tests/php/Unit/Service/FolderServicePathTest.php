<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FolderService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\IRootFolder;
use OCP\Files\ISetupManager;
use OCP\Files\IUserFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;

final class FolderServicePathTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IRootFolder $root;
	private IUserFolder $userFolder;
	private FolderService $service;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$this->root = $this->createMock(IRootFolder::class);
		$this->userFolder = $this->createMock(IUserFolder::class);
		$this->root->method('getUserFolder')->with('alice')->willReturn($this->userFolder);

		$appDataFactory = $this->createMock(IAppDataFactory::class);
		$appDataFactory->method('get')->with('libresign')->willReturn($this->createMock(IAppData::class));
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$this->service = new FolderService(
			$this->root,
			$appDataFactory,
			$this->createMock(IGroupManager::class),
			$this->createMock(IAppConfig::class),
			$l10n,
			$this->createMock(ISetupManager::class),
			$this->createMock(IUserManager::class),
			null,
		);
	}

	public function testGetsPathFromExplicitUserWithoutChangingServiceState(): void {
		$node = $this->createMock(Node::class);
		$this->userFolder->expects($this->once())->method('get')->with('/Documents/contract.pdf')->willReturn($node);

		$this->assertSame($node, $this->service->getFileByPath('/Documents/contract.pdf', 'alice'));
		$this->assertNull($this->service->getUserId());
	}

	public function testTranslatesMissingPathToDomainException(): void {
		$this->userFolder->method('get')->willThrowException(new NotFoundException());

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(404);
		$this->expectExceptionMessage('Invalid data to validate file');
		$this->service->getFileByPath('/missing.pdf', 'alice');
	}
}
