<?php

namespace OCA\Libresign\Tests\Unit\Helper;

use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\FolderService;
use OCP\IL10N;

final class ValidateHelperTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/** @var IL10N */
	private $l10n;
	/** @var FileUserMapper */
	private $fileUser;
	/** @var FolderService */
	private $folder;

	public function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->fileUser = $this->createMock(FileUserMapper::class);
		$this->folder = $this->createMock(FolderService::class);
		$this->validateHelper = new ValidateHelper(
			$this->l10n,
			$this->fileUser,
			$this->folder
		);
	}

	public function testValidateFileWithoutAllNecessaryData() {
		$this->expectExceptionMessage('Inform URL or base64 or fileID to sign');
		$this->validateHelper->validateFile([
			'file' => ['invalid'],
			'name' => 'test'
		]);
	}

	public function testValidateFileWithInvalidFileId() {
		$this->expectExceptionMessage('Invalid fileID');
		$this->validateHelper->validateFile([
			'file' => ['fileId' => 'invalid'],
			'name' => 'test'
		]);
	}

	public function testValidateFileWhenFileIdDoesNotExist() {
		$this->expectExceptionMessage('Invalid fileID');
		$this->validateHelper->validateFile([
			'file' => ['fileId' => 123],
			'name' => 'test'
		]);
	}

	public function testValidateFileByNodeIdWhenAlreadyAskedToSignThisDocument() {
		$this->fileUser->method('getByNodeId')->will($this->returnValue('exists'));
		$this->expectExceptionMessage('Already asked to sign this document');
		$this->validateHelper->validateFileByNodeId(1);
	}

	public function testValidateFileByNodeIdWhenFileIdNotExists() {
		$this->fileUser->method('getByNodeId')->will($this->returnCallback(function () {
			throw new \Exception('not found');
		}));
		$this->expectExceptionMessage('Invalid fileID');
		$this->validateHelper->validateFileByNodeId(1);
	}

	public function testValidateFileByNodeIdWhenFileNotExists() {
		$this->fileUser->method('getByNodeId')->will($this->returnCallback(function () {
			throw new \Exception('not found');
		}));
		$folder = $this->createMock(\OCP\Files\IRootFolder::class);
		$folder->method('getById')->will($this->returnValue(null));
		$this->folder->method('getFolder')->will($this->returnValue($folder));
		$this->expectExceptionMessage('Invalid fileID');
		$this->validateHelper->validateFileByNodeId(1);
	}

	public function testValidateFileByNodeIdWhenFileIsNotPDF() {
		$folder = $this->createMock(\OCP\Files\IRootFolder::class);
		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getMimeType')->will($this->returnValue('html'));
		$folder->method('getById')->will($this->returnValue([$file]));
		$this->folder->method('getFolder')->will($this->returnValue($folder));
		$this->expectExceptionMessage('Must be a fileID of a PDF');
		$this->validateHelper->validateFileByNodeId(1);
	}

	public function testValidateFileByNodeIdWhenSuccess() {
		$folder = $this->createMock(\OCP\Files\IRootFolder::class);
		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getMimeType')->will($this->returnValue('application/pdf'));
		$folder->method('getById')->will($this->returnValue([$file]));
		$this->folder->method('getFolder')->will($this->returnValue($folder));
		$actual = $this->validateHelper->validateFileByNodeId(1);
		$this->assertNull($actual);
	}
}
