<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\NodeType;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\SignedCopyAttachmentProvider;
use OCP\Files\File;
use PHPUnit\Framework\MockObject\MockObject;

final class SignedCopyAttachmentProviderTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private FileMapper&MockObject $fileMapper;
	private FolderService&MockObject $folderService;
	/** @var array<int, FileEntity> */
	private array $files = [];
	/** @var array<int, File> */
	private array $nodes = [];

	public function setUp(): void {
		parent::setUp();
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->fileMapper->method('getById')
			->willReturnCallback(fn (int $id): FileEntity => $this->files[$id]);
		$this->fileMapper->method('getChildrenFiles')
			->willReturnCallback(fn (int $id): array => array_values(array_filter(
				$this->files,
				static fn (FileEntity $file): bool => $file->getParentFileId() === $id,
			)));
		$this->folderService->method('getReadableNodeById')
			->willReturnCallback(fn (string $userId, int $nodeId): ?File => $this->nodes[$nodeId] ?? null);
	}

	private function getProvider(): SignedCopyAttachmentProvider {
		return new SignedCopyAttachmentProvider($this->fileMapper, $this->folderService);
	}

	public function testLoadsSignedDocument(): void {
		$file = $this->addDocument(1, 'contract.pdf', 100);

		$this->assertSame(['contract.pdf' => 'content-1'], $this->getProvider()->load($file, [1]));
	}

	public function testLoadsOnlyRequestedEnvelopeDocuments(): void {
		$envelope = $this->addEnvelope(1);
		$this->addDocument(2, 'first.pdf', 100, 1);
		$this->addDocument(3, 'second.pdf', 100, 1);

		$this->assertSame(['second.pdf' => 'content-3'], $this->getProvider()->load($envelope, [3]));
		$this->assertSame(
			['first.pdf' => 'content-2', 'second.pdf' => 'content-3'],
			$this->getProvider()->load($envelope, [2, 3]),
		);
	}

	public function testIgnoresDocumentsOfAnotherRequest(): void {
		$file = $this->addDocument(1, 'contract.pdf', 100);
		$this->addDocument(9, 'other.pdf', 100);

		$this->assertSame([], $this->getProvider()->load($file, [9]));
	}

	public function testReturnsNothingWhenDocumentsAreTooLarge(): void {
		$envelope = $this->addEnvelope(1);
		$half = intdiv(SignedCopyAttachmentProvider::MAX_ATTACHMENT_BYTES, 2);
		$this->addDocument(2, 'first.pdf', $half, 1);
		$this->addDocument(3, 'second.pdf', $half + 1, 1);

		$this->assertSame([], $this->getProvider()->load($envelope, [2, 3]));
		$this->assertSame(['first.pdf' => 'content-2'], $this->getProvider()->load($envelope, [2]));
	}

	public function testReturnsNothingWhenSignedFileIsMissing(): void {
		$file = $this->addDocument(1, 'contract.pdf', 100);
		unset($this->nodes[1001]);

		$this->assertSame([], $this->getProvider()->load($file, [1]));
	}

	public function testRenamesDuplicateFileNames(): void {
		$envelope = $this->addEnvelope(1);
		$this->addDocument(2, 'contract.pdf', 100, 1);
		$this->addDocument(3, 'contract.pdf', 100, 1);

		$this->assertSame(
			['contract.pdf' => 'content-2', 'contract (2).pdf' => 'content-3'],
			$this->getProvider()->load($envelope, [2, 3]),
		);
	}

	private function addEnvelope(int $id): FileEntity {
		$envelope = new FileEntity();
		$envelope->setId($id);
		$envelope->setUserId('requester');
		$envelope->setNodeTypeEnum(NodeType::ENVELOPE);
		$this->files[$id] = $envelope;
		return $envelope;
	}

	private function addDocument(int $id, string $name, int $size, ?int $parentId = null): FileEntity {
		$file = new FileEntity();
		$file->setId($id);
		$file->setUserId('requester');
		$file->setNodeTypeEnum(NodeType::FILE);
		$file->setSignedNodeId(1000 + $id);
		if ($parentId !== null) {
			$file->setParentFileId($parentId);
		}
		$this->files[$id] = $file;
		$node = $this->createMock(File::class);
		$node->method('getName')->willReturn($name);
		$node->method('getSize')->willReturn($size);
		$node->method('getContent')->willReturn('content-' . $id);
		$this->nodes[1000 + $id] = $node;
		return $file;
	}
}
