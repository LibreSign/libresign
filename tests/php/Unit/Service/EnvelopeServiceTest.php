<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\NodeType;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\EnvelopeService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\Files\Folder;
use OCP\IAppConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;

final class EnvelopeServiceTest extends TestCase {
	private FileMapper&MockObject $fileMapper;
	private IL10N $l10n;
	private IAppConfig $appConfig;
	private FolderService&MockObject $folderService;
	private EnvelopeService $service;

	public function setUp(): void {
		parent::setUp();
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->l10n = \OCP\Server::get(\OCP\L10N\IFactory::class)->get(Application::APP_ID);
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->folderService = $this->createMock(FolderService::class);

		$this->service = new EnvelopeService(
			$this->fileMapper,
			$this->l10n,
			$this->appConfig,
			$this->folderService,
		);
	}

	public function testEnvelopeIsCreatedAsDraft(): void {
		$this->fileMapper->method('insert')->willReturnArgument(0);

		$mockFolder = $this->createMock(Folder::class);
		$mockEnvelopeFolder = $this->createMock(Folder::class);
		$mockEnvelopeFolder->method('getId')->willReturn(999);
		$mockFolder->method('newFolder')->willReturn($mockEnvelopeFolder);
		$this->folderService->method('getFolder')->willReturn($mockFolder);

		$envelope = $this->service->createEnvelope('Contract Package');

		$this->assertSame(FileEntity::STATUS_DRAFT, $envelope->getStatus());
	}

	public function testEnvelopeIsCreatedWithEnvelopeType(): void {
		$this->fileMapper->method('insert')->willReturnArgument(0);

		$mockFolder = $this->createMock(Folder::class);
		$mockEnvelopeFolder = $this->createMock(Folder::class);
		$mockEnvelopeFolder->method('getId')->willReturn(999);
		$mockFolder->method('newFolder')->willReturn($mockEnvelopeFolder);
		$this->folderService->method('getFolder')->willReturn($mockFolder);

		$envelope = $this->service->createEnvelope('Contract Package');

		$this->assertTrue($envelope->isEnvelope());
	}

	public function testCannotAddFileToRegularFile(): void {
		$this->expectException(LibresignException::class);

		$regularFile = new FileEntity();
		$regularFile->setNodeTypeEnum(NodeType::FILE);

		$this->fileMapper->method('getById')->willReturn($regularFile);

		$this->service->addFileToEnvelope(1, new FileEntity());
	}

	public function testCannotAddFileToEnvelopeAfterSigningStarts(): void {
		$this->expectException(LibresignException::class);

		$envelope = new FileEntity();
		$envelope->setNodeTypeEnum(NodeType::ENVELOPE);
		$envelope->setStatus(FileEntity::STATUS_ABLE_TO_SIGN);

		$this->fileMapper->method('getById')->willReturn($envelope);

		$this->service->addFileToEnvelope(1, new FileEntity());
	}

	public function testCannotExceedMaximumFilesPerEnvelope(): void {
		$this->expectException(LibresignException::class);

		$envelope = new FileEntity();
		$envelope->setNodeTypeEnum(NodeType::ENVELOPE);
		$envelope->setStatus(FileEntity::STATUS_DRAFT);

		$this->fileMapper->method('getById')->willReturn($envelope);
		$this->fileMapper->method('countChildrenFiles')->willReturn(50);

		$this->service->addFileToEnvelope(1, new FileEntity());
	}

	public function testFileIsLinkedToEnvelopeWhenAdded(): void {
		$envelopeId = 100;
		$envelope = new FileEntity();
		$envelope->setId($envelopeId);
		$envelope->setNodeTypeEnum(NodeType::ENVELOPE);
		$envelope->setStatus(FileEntity::STATUS_DRAFT);

		$file = new FileEntity();

		$this->fileMapper->method('getById')->willReturn($envelope);
		$this->fileMapper->method('countChildrenFiles')->willReturn(0);
		$this->fileMapper->method('update')->willReturnArgument(0);

		$result = $this->service->addFileToEnvelope($envelopeId, $file);

		$this->assertSame($envelopeId, $result->getParentFileId());
	}

	public function testFileBecomesRegularFileTypeWhenAddedToEnvelope(): void {
		$envelope = new FileEntity();
		$envelope->setId(1);
		$envelope->setNodeTypeEnum(NodeType::ENVELOPE);
		$envelope->setStatus(FileEntity::STATUS_DRAFT);

		$file = new FileEntity();

		$this->fileMapper->method('getById')->willReturn($envelope);
		$this->fileMapper->method('countChildrenFiles')->willReturn(0);
		$this->fileMapper->method('update')->willReturnArgument(0);

		$result = $this->service->addFileToEnvelope(1, $file);

		$this->assertSame(NodeType::FILE, $result->getNodeTypeEnum());
	}

	public function testReturnsNullWhenFileHasNoEnvelope(): void {
		$this->fileMapper->method('getParentEnvelope')
			->willThrowException(new \OCP\AppFramework\Db\DoesNotExistException(''));

		$result = $this->service->getEnvelopeByFileId(999);

		$this->assertNull($result);
	}

	public function testReturnsEnvelopeWhenFileHasParent(): void {
		$expectedEnvelope = new FileEntity();
		$expectedEnvelope->setId(5);
		$expectedEnvelope->setNodeTypeEnum(NodeType::ENVELOPE);

		$this->fileMapper->method('getParentEnvelope')->willReturn($expectedEnvelope);

		$result = $this->service->getEnvelopeByFileId(10);

		$this->assertNotNull($result);
		$this->assertSame(5, $result->getId());
	}
}

