<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Envelope\EnvelopeFileRelocator;
use OCA\Libresign\Service\FolderService;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;

class EnvelopeFileRelocatorTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private FolderService&MockObject $folderService;
	private EnvelopeFileRelocator $relocator;

	public function setUp(): void {
		parent::setUp();
		$this->folderService = $this->createMock(FolderService::class);
		$this->relocator = new EnvelopeFileRelocator($this->folderService);
	}

	public function testReturnsOriginalWhenAlreadyInside(): void {
		$sourceFile = $this->createMock(\OCP\Files\File::class);
		$sourceFile->method('getPath')->willReturn('/user/files/Envelope/doc.pdf');

		$envelopeFolder = $this->createMock(Folder::class);
		$envelopeFolder->method('getPath')->willReturn('/user/files/Envelope');

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder->method('getFirstNodeById')->with(10)->willReturn($envelopeFolder);

		$this->folderService->expects($this->once())->method('setUserId')->with('u1');
		$this->folderService->method('getUserRootFolder')->willReturn($rootFolder);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('u1');

		$result = $this->relocator->ensureFileInEnvelopeFolder($sourceFile, 10, $user);
		self::assertSame($sourceFile, $result);
	}

	public function testCopiesFileWhenOutside(): void {
		$sourceFile = $this->createMock(\OCP\Files\File::class);
		$sourceFile->method('getPath')->willReturn('/user/files/Other/doc.pdf');
		$sourceFile->method('getName')->willReturn('doc.pdf');
		$sourceFile->method('getContent')->willReturn('content');

		$copiedFile = $this->createMock(\OCP\Files\File::class);

		$envelopeFolder = $this->createMock(Folder::class);
		$envelopeFolder->method('getPath')->willReturn('/user/files/Envelope');
		$envelopeFolder->expects($this->once())
			->method('newFile')
			->with('doc.pdf', 'content')
			->willReturn($copiedFile);

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder->method('getFirstNodeById')->with(10)->willReturn($envelopeFolder);

		$this->folderService->expects($this->once())->method('setUserId')->with('u1');
		$this->folderService->method('getUserRootFolder')->willReturn($rootFolder);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('u1');

		$result = $this->relocator->ensureFileInEnvelopeFolder($sourceFile, 10, $user);
		self::assertSame($copiedFile, $result);
	}

	public function testThrowsWhenEnvelopeFolderNotFound(): void {
		$sourceFile = $this->createMock(\OCP\Files\File::class);
		$sourceFile->method('getPath')->willReturn('/user/files/doc.pdf');

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder->method('getFirstNodeById')->with(10)->willReturn($this->createMock(Node::class));

		$this->folderService->method('setUserId');
		$this->folderService->method('getUserRootFolder')->willReturn($rootFolder);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('u1');

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Envelope folder not found');
		$this->relocator->ensureFileInEnvelopeFolder($sourceFile, 10, $user);
	}

	public function testThrowsWhenSourceIsNotFile(): void {
		$sourceNode = $this->createMock(Node::class);
		$sourceNode->method('getPath')->willReturn('/user/files/Other');

		$envelopeFolder = $this->createMock(Folder::class);
		$envelopeFolder->method('getPath')->willReturn('/user/files/Envelope');

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder->method('getFirstNodeById')->with(10)->willReturn($envelopeFolder);

		$this->folderService->method('setUserId');
		$this->folderService->method('getUserRootFolder')->willReturn($rootFolder);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('u1');

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Invalid file type for envelope');
		$this->relocator->ensureFileInEnvelopeFolder($sourceNode, 10, $user);
	}
}
