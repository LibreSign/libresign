<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\FileUploadHelper;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\File\MimeService;
use OCA\Libresign\Service\File\PdfValidator;
use OCA\Libresign\Service\File\UploadProcessor;
use OCA\Libresign\Service\FolderService;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class UploadProcessorTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private FileUploadHelper|MockObject $uploadHelper;
	private FolderService|MockObject $folderService;
	private MimeService|MockObject $mimeService;
	private PdfValidator|MockObject $pdfValidator;
	private ValidateHelper|MockObject $validateHelper;
	private LoggerInterface|MockObject $logger;

	public function setUp(): void {
		parent::setUp();
		$this->uploadHelper = $this->createMock(FileUploadHelper::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->mimeService = $this->createMock(MimeService::class);
		$this->pdfValidator = $this->createMock(PdfValidator::class);
		$this->validateHelper = $this->createMock(ValidateHelper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function getService(): UploadProcessor {
		return new UploadProcessor(
			$this->uploadHelper,
			$this->folderService,
			$this->mimeService,
			$this->pdfValidator,
			$this->validateHelper,
			$this->logger,
		);
	}

	public function testGetNodeFromUploadedFileSuccess(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('test-user');

		$uploadedFile = [
			'name' => 'test.pdf',
			'tmp_name' => '/tmp/test.pdf',
			'size' => 1024,
		];

		$content = 'PDF content';
		$extension = 'pdf';

		$this->uploadHelper->method('validateUploadedFile');
		$this->uploadHelper->method('readUploadedFile')->willReturn($content);
		$this->mimeService->method('getExtension')->with($content)->willReturn($extension);
		$this->pdfValidator->method('validate');

		$folder = $this->createMock(Folder::class);
		$this->folderService->method('getFolder')->willReturn($folder);
		$this->folderService->method('getFolderName')->willReturn('LibreSign');

		$targetFolder = $this->createMock(Folder::class);
		$folder->method('newFolder')->willReturn($targetFolder);

		$node = $this->createMock(Node::class);
		$targetFolder->method('newFile')->willReturn($node);

		$data = [
			'userManager' => $user,
			'name' => 'test',
			'uploadedFile' => $uploadedFile,
			'settings' => [],
		];

		$service = $this->getService();
		$result = $service->getNodeFromUploadedFile($data);

		$this->assertSame($node, $result);
	}

	public function testGetNodeFromUploadedFileValidatesUpload(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('test-user');

		$uploadedFile = [
			'name' => 'test.pdf',
			'tmp_name' => '/tmp/test.pdf',
		];

		$this->uploadHelper
			->expects($this->once())
			->method('validateUploadedFile')
			->with($uploadedFile);

		$this->uploadHelper->method('readUploadedFile')->willReturn('content');
		$this->mimeService->method('getExtension')->willReturn('pdf');
		$this->pdfValidator->method('validate');

		$folder = $this->createMock(Folder::class);
		$this->folderService->method('getFolder')->willReturn($folder);
		$this->folderService->method('getFolderName')->willReturn('LibreSign');

		$targetFolder = $this->createMock(Folder::class);
		$folder->method('newFolder')->willReturn($targetFolder);

		$node = $this->createMock(Node::class);
		$targetFolder->method('newFile')->willReturn($node);

		$data = [
			'userManager' => $user,
			'name' => 'test',
			'uploadedFile' => $uploadedFile,
			'settings' => [],
		];

		$service = $this->getService();
		$service->getNodeFromUploadedFile($data);
	}

	public function testGetNodeFromUploadedFileValidatesPdf(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('test-user');

		$uploadedFile = [
			'name' => 'test.pdf',
			'tmp_name' => '/tmp/test.pdf',
		];

		$content = 'PDF content';

		$this->uploadHelper->method('validateUploadedFile');
		$this->uploadHelper->method('readUploadedFile')->willReturn($content);
		$this->mimeService->method('getExtension')->willReturn('pdf');

		$this->pdfValidator
			->expects($this->once())
			->method('validate')
			->with($content);

		$folder = $this->createMock(Folder::class);
		$this->folderService->method('getFolder')->willReturn($folder);
		$this->folderService->method('getFolderName')->willReturn('LibreSign');

		$targetFolder = $this->createMock(Folder::class);
		$folder->method('newFolder')->willReturn($targetFolder);

		$node = $this->createMock(Node::class);
		$targetFolder->method('newFile')->willReturn($node);

		$data = [
			'userManager' => $user,
			'name' => 'test',
			'uploadedFile' => $uploadedFile,
			'settings' => [],
		];

		$service = $this->getService();
		$service->getNodeFromUploadedFile($data);
	}

	public function testProcessUploadedFilesWithRollbackSuccess(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('test-user');

		$files = [
			['name' => 'file1.pdf', 'tmp_name' => '/tmp/file1.pdf'],
			['name' => 'file2.pdf', 'tmp_name' => '/tmp/file2.pdf'],
		];

		$this->uploadHelper->method('validateUploadedFile');
		$this->uploadHelper->method('readUploadedFile')->willReturn('content');
		$this->mimeService->method('getExtension')->willReturn('pdf');
		$this->pdfValidator->method('validate');
		$this->validateHelper->method('validateNewFile');

		$folder = $this->createMock(Folder::class);
		$this->folderService->method('getFolder')->willReturn($folder);
		$this->folderService->method('getFolderName')->willReturn('LibreSign');

		$targetFolder = $this->createMock(Folder::class);
		$folder->method('newFolder')->willReturn($targetFolder);

		$node = $this->createMock(Node::class);
		$node->method('getId')->willReturn(123);
		$targetFolder->method('newFile')->willReturn($node);

		$service = $this->getService();
		$result = $service->processUploadedFilesWithRollback($files, $user, []);

		$this->assertCount(2, $result);
		$this->assertArrayHasKey('fileNode', $result[0]);
		$this->assertArrayHasKey('name', $result[0]);
		$this->assertArrayHasKey('fileNode', $result[1]);
		$this->assertArrayHasKey('name', $result[1]);
	}

	public function testProcessUploadedFilesWithRollbackValidatesNewFiles(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('test-user');

		$files = [
			['name' => 'file1.pdf', 'tmp_name' => '/tmp/file1.pdf'],
		];

		$this->uploadHelper->method('validateUploadedFile');
		$this->uploadHelper->method('readUploadedFile')->willReturn('content');
		$this->mimeService->method('getExtension')->willReturn('pdf');
		$this->pdfValidator->method('validate');

		$this->validateHelper
			->expects($this->once())
			->method('validateNewFile')
			->with([
				'file' => ['fileId' => 123],
				'userManager' => $user,
			]);

		$folder = $this->createMock(Folder::class);
		$this->folderService->method('getFolder')->willReturn($folder);
		$this->folderService->method('getFolderName')->willReturn('LibreSign');

		$targetFolder = $this->createMock(Folder::class);
		$folder->method('newFolder')->willReturn($targetFolder);

		$node = $this->createMock(Node::class);
		$node->method('getId')->willReturn(123);
		$targetFolder->method('newFile')->willReturn($node);

		$service = $this->getService();
		$service->processUploadedFilesWithRollback($files, $user, []);
	}

	public function testProcessUploadedFilesWithRollbackOnValidationError(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('test-user');

		$files = [
			['name' => 'file1.pdf', 'tmp_name' => '/tmp/file1.pdf'],
		];

		$this->uploadHelper->method('validateUploadedFile');
		$this->uploadHelper->method('readUploadedFile')->willReturn('content');
		$this->mimeService->method('getExtension')->willReturn('pdf');
		$this->pdfValidator->method('validate');

		$this->validateHelper
			->method('validateNewFile')
			->willThrowException(new LibresignException('Invalid file'));

		$folder = $this->createMock(Folder::class);
		$this->folderService->method('getFolder')->willReturn($folder);
		$this->folderService->method('getFolderName')->willReturn('LibreSign');

		$targetFolder = $this->createMock(Folder::class);
		$folder->method('newFolder')->willReturn($targetFolder);

		$node = $this->createMock(Node::class);
		$node->method('getId')->willReturn(123);
		$node
			->expects($this->once())
			->method('delete');

		$targetFolder->method('newFile')->willReturn($node);

		$this->expectException(LibresignException::class);

		$service = $this->getService();
		$service->processUploadedFilesWithRollback($files, $user, []);
	}

	public function testProcessUploadedFilesWithRollbackLogsDeleteError(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('test-user');

		$files = [
			['name' => 'file1.pdf', 'tmp_name' => '/tmp/file1.pdf'],
		];

		$this->uploadHelper->method('validateUploadedFile');
		$this->uploadHelper->method('readUploadedFile')->willReturn('content');
		$this->mimeService->method('getExtension')->willReturn('pdf');
		$this->pdfValidator->method('validate');

		$this->validateHelper
			->method('validateNewFile')
			->willThrowException(new LibresignException('Invalid file'));

		$folder = $this->createMock(Folder::class);
		$this->folderService->method('getFolder')->willReturn($folder);
		$this->folderService->method('getFolderName')->willReturn('LibreSign');

		$targetFolder = $this->createMock(Folder::class);
		$folder->method('newFolder')->willReturn($targetFolder);

		$node = $this->createMock(Node::class);
		$node->method('getId')->willReturn(123);
		$node
			->method('delete')
			->willThrowException(new \Exception('Delete failed'));

		$this->logger
			->expects($this->once())
			->method('error')
			->with(
				'Failed to rollback uploaded file',
				$this->callback(function ($context) {
					return isset($context['nodeId']) && isset($context['error']);
				})
			);

		$targetFolder->method('newFile')->willReturn($node);

		$this->expectException(LibresignException::class);

		$service = $this->getService();
		$service->processUploadedFilesWithRollback($files, $user, []);
	}

	public function testProcessUploadedFilesReturnsCorrectStructure(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('test-user');

		$files = [
			['name' => 'document.pdf', 'tmp_name' => '/tmp/file.pdf'],
		];

		$this->uploadHelper->method('validateUploadedFile');
		$this->uploadHelper->method('readUploadedFile')->willReturn('content');
		$this->mimeService->method('getExtension')->willReturn('pdf');
		$this->pdfValidator->method('validate');
		$this->validateHelper->method('validateNewFile');

		$folder = $this->createMock(Folder::class);
		$this->folderService->method('getFolder')->willReturn($folder);
		$this->folderService->method('getFolderName')->willReturn('LibreSign');

		$targetFolder = $this->createMock(Folder::class);
		$folder->method('newFolder')->willReturn($targetFolder);

		$node = $this->createMock(Node::class);
		$node->method('getId')->willReturn(123);
		$targetFolder->method('newFile')->willReturn($node);

		$service = $this->getService();
		$result = $service->processUploadedFilesWithRollback($files, $user, []);

		$this->assertCount(1, $result);
		$this->assertSame($node, $result[0]['fileNode']);
		$this->assertEquals('document', $result[0]['name']);
	}
}
