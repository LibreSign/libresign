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
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\NodeType;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Envelope\EnvelopeService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\Files\Folder;
use OCP\IAppConfig;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
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

		$envelope = $this->service->createEnvelope('Contract Package', 'testuser');

		$this->assertSame(FileStatus::DRAFT->value, $envelope->getStatus());
	}

	public function testEnvelopeIsCreatedWithEnvelopeType(): void {
		$this->fileMapper->method('insert')->willReturnArgument(0);

		$mockFolder = $this->createMock(Folder::class);
		$mockEnvelopeFolder = $this->createMock(Folder::class);
		$mockEnvelopeFolder->method('getId')->willReturn(999);
		$mockFolder->method('newFolder')->willReturn($mockEnvelopeFolder);
		$this->folderService->method('getFolder')->willReturn($mockFolder);

		$envelope = $this->service->createEnvelope('Contract Package', 'testuser');

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
		$envelope->setStatus(FileStatus::ABLE_TO_SIGN->value);

		$this->fileMapper->method('getById')->willReturn($envelope);

		$this->service->addFileToEnvelope(1, new FileEntity());
	}

	public function testCannotExceedMaximumFilesPerEnvelope(): void {
		$this->expectException(LibresignException::class);

		$envelope = new FileEntity();
		$envelope->setNodeTypeEnum(NodeType::ENVELOPE);
		$envelope->setStatus(FileStatus::DRAFT->value);

		$this->fileMapper->method('getById')->willReturn($envelope);
		$this->fileMapper->method('countChildrenFiles')->willReturn(50);

		$this->service->addFileToEnvelope(1, new FileEntity());
	}

	public function testFileIsLinkedToEnvelopeWhenAdded(): void {
		$envelopeId = 100;
		$envelope = new FileEntity();
		$envelope->setId($envelopeId);
		$envelope->setNodeTypeEnum(NodeType::ENVELOPE);
		$envelope->setStatus(FileStatus::DRAFT->value);

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
		$envelope->setStatus(FileStatus::DRAFT->value);

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

	public function testEnvelopeUuidMatchesFolderName(): void {
		$this->fileMapper->method('insert')->willReturnArgument(0);

		$mockFolder = $this->createMock(Folder::class);
		$mockEnvelopeFolder = $this->createMock(Folder::class);
		$mockEnvelopeFolder->method('getId')->willReturn(999);

		$capturedFolderName = '';
		$mockFolder->method('newFolder')->willReturnCallback(
			function ($folderName) use ($mockEnvelopeFolder, &$capturedFolderName) {
				$capturedFolderName = $folderName;
				return $mockEnvelopeFolder;
			}
		);

		$this->folderService->method('getFolder')->willReturn($mockFolder);

		$envelope = $this->service->createEnvelope('Contract', 'user1');

		$this->assertStringStartsWith('Contract_', $capturedFolderName);
		$this->assertStringContainsString($envelope->getUuid(), $capturedFolderName);
	}

	#[DataProvider('envelopeCreationProvider')]
	public function testEnvelopeCreationWithCustomPathOrDefaultNaming(
		string $name,
		string $userId,
		int $filesCount,
		?string $customPath,
		bool $expectCustomPath,
		int $expectedNodeId,
	): void {
		$this->fileMapper->method('insert')->willReturnArgument(0);

		if ($expectCustomPath) {
			$mockEnvelopeFolder = $this->createMock(Folder::class);
			$mockEnvelopeFolder->method('getId')->willReturn($expectedNodeId);

			$this->folderService
				->expects($this->once())
				->method('getOrCreateFolderByAbsolutePath')
				->with($customPath)
				->willReturn($mockEnvelopeFolder);

			$envelope = $this->service->createEnvelope($name, $userId, $filesCount, $customPath);

			$this->assertSame($expectedNodeId, $envelope->getNodeId());
			$this->assertSame($name, $envelope->getName());
			$this->assertSame($userId, $envelope->getUserId());
			$this->assertSame(['filesCount' => $filesCount], $envelope->getMetadata());
		} else {
			$mockDefaultFolder = $this->createMock(Folder::class);
			$mockEnvelopeFolder = $this->createMock(Folder::class);
			$mockEnvelopeFolder->method('getId')->willReturn($expectedNodeId);

			$this->folderService
				->expects($this->once())
				->method('getFolder')
				->willReturn($mockDefaultFolder);

			$capturedFolderName = '';
			$mockDefaultFolder->method('newFolder')
				->willReturnCallback(function ($folderName) use ($mockEnvelopeFolder, &$capturedFolderName) {
					$capturedFolderName = $folderName;
					return $mockEnvelopeFolder;
				});

			$envelope = $this->service->createEnvelope($name, $userId, $filesCount, $customPath);

			$this->assertStringStartsWith($name . '_', $capturedFolderName);
			$this->assertStringContainsString($envelope->getUuid(), $capturedFolderName);
			$this->assertSame($expectedNodeId, $envelope->getNodeId());
			$this->assertSame($name, $envelope->getName());
		}

		$this->assertTrue($envelope->isEnvelope());
		$this->assertSame(FileStatus::DRAFT->value, $envelope->getStatus());
	}

	public static function envelopeCreationProvider(): array {
		return [
			'custom path - root level' => [
				'name' => 'Root Envelope',
				'userId' => 'user1',
				'filesCount' => 2,
				'customPath' => '/EnvelopeAtRoot',
				'expectCustomPath' => true,
				'expectedNodeId' => 100,
			],
			'custom path - nested' => [
				'name' => 'Legal Contract',
				'userId' => 'user2',
				'filesCount' => 5,
				'customPath' => '/Documents/Legal/Contracts/2026',
				'expectCustomPath' => true,
				'expectedNodeId' => 200,
			],
			'custom path - with spaces' => [
				'name' => 'Important Files',
				'userId' => 'user3',
				'filesCount' => 3,
				'customPath' => '/My Documents/Important Files',
				'expectCustomPath' => true,
				'expectedNodeId' => 300,
			],
			'default path - no custom path provided' => [
				'name' => 'Standard Envelope',
				'userId' => 'user4',
				'filesCount' => 1,
				'customPath' => null,
				'expectCustomPath' => false,
				'expectedNodeId' => 888,
			],
			'default path - single file' => [
				'name' => 'Contract Package',
				'userId' => 'testuser',
				'filesCount' => 1,
				'customPath' => null,
				'expectCustomPath' => false,
				'expectedNodeId' => 999,
			],
		];
	}

	public function testEnvelopeCreationFailsWhenCustomPathNotEmpty(): void {
		$this->expectException(LibresignException::class);

		$this->folderService
			->method('getOrCreateFolderByAbsolutePath')
			->willThrowException(new LibresignException('Folder not empty'));

		$this->service->createEnvelope('Test', 'user', 1, '/Documents/Existing');
	}

	#[DataProvider('envelopeConstraintsProvider')]
	public function testValidateEnvelopeConstraints(
		int $fileCount,
		bool $shouldPass,
	): void {
		if (!$shouldPass) {
			$this->expectException(LibresignException::class);
		}

		$this->service->validateEnvelopeConstraints($fileCount);

		if ($shouldPass) {
			$this->assertTrue(true);
		}
	}

	public static function envelopeConstraintsProvider(): array {
		return [
			'valid - 1 file' => [1, true],
			'valid - 10 files' => [10, true],
			'valid - exactly max (50)' => [50, true],
			'invalid - exceeds max' => [51, false],
			'invalid - way over max' => [100, false],
		];
	}
}
