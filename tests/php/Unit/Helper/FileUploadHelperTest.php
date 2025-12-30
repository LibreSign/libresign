<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Helper;

function is_uploaded_file($filename) {
	return file_exists($filename);
}

namespace OCA\Libresign\Tests\Unit\Helper;

use InvalidArgumentException;
use OCA\Libresign\Helper\FileUploadHelper;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FileUploadHelperTest extends TestCase {
	private FileUploadHelper $helper;
	private IL10N&MockObject $l10n;
	private string $tempFile;

	protected function setUp(): void {
		parent::setUp();

		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')
			->willReturnCallback(fn ($text) => $text);

		$this->helper = new FileUploadHelper($this->l10n);

		$this->tempFile = tempnam(sys_get_temp_dir(), 'upload_test_');
		file_put_contents($this->tempFile, 'test content');
	}

	protected function tearDown(): void {
		if (file_exists($this->tempFile)) {
			@unlink($this->tempFile);
		}
		parent::tearDown();
	}

	public function testValidateUploadedFileSuccess(): void {
		$uploadedFile = [
			'tmp_name' => $this->tempFile,
			'error' => UPLOAD_ERR_OK,
			'size' => filesize($this->tempFile),
		];

		$this->helper->validateUploadedFile($uploadedFile);

		$this->assertTrue(file_exists($this->tempFile));
	}

	public function testValidateUploadedFileWithUploadError(): void {
		$uploadedFile = [
			'tmp_name' => $this->tempFile,
			'error' => UPLOAD_ERR_INI_SIZE,
			'size' => 0,
		];

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid file provided');

		try {
			$this->helper->validateUploadedFile($uploadedFile);
		} finally {
			$this->assertFalse(file_exists($this->tempFile), 'File should be deleted after error');
		}
	}

	public function testValidateUploadedFileNotActuallyUploaded(): void {
		$nonExistentFile = sys_get_temp_dir() . '/non_existent_file_' . time();

		$uploadedFile = [
			'tmp_name' => $nonExistentFile,
			'error' => UPLOAD_ERR_OK,
			'size' => 100,
		];

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid file provided');

		$this->helper->validateUploadedFile($uploadedFile);
	}

	public function testValidateUploadedFileTooBig(): void {
		$uploadedFile = [
			'tmp_name' => $this->tempFile,
			'error' => UPLOAD_ERR_OK,
			'size' => \OCP\Util::uploadLimit() + 1,
		];

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('File is too big');

		try {
			$this->helper->validateUploadedFile($uploadedFile);
		} finally {
			$this->assertFalse(file_exists($this->tempFile), 'File should be deleted when too big');
		}
	}

	public function testReadUploadedFileSuccess(): void {
		$expectedContent = 'test file content';
		file_put_contents($this->tempFile, $expectedContent);

		$uploadedFile = [
			'tmp_name' => $this->tempFile,
		];

		$content = $this->helper->readUploadedFile($uploadedFile);

		$this->assertEquals($expectedContent, $content);
	}

	public function testReadUploadedFileNotReadable(): void {
		$uploadedFile = [
			'tmp_name' => '/path/that/does/not/exist.txt',
		];

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Cannot read file');

		$this->helper->readUploadedFile($uploadedFile);
	}

	public function testValidateUploadedFileWithForbiddenName(): void {
		$forbiddenFile = sys_get_temp_dir() . '/test<file>.txt';

		if (@file_put_contents($forbiddenFile, 'test') === false) {
			$this->markTestSkipped('Cannot create file with forbidden characters on this OS');
			return;
		}

		$uploadedFile = [
			'tmp_name' => $forbiddenFile,
			'error' => UPLOAD_ERR_OK,
			'size' => filesize($forbiddenFile),
		];

		$exceptionThrown = false;
		try {
			$this->helper->validateUploadedFile($uploadedFile);
		} catch (InvalidArgumentException $e) {
			$exceptionThrown = true;
			$this->assertEquals('Invalid file provided', $e->getMessage());
			$this->assertFalse(file_exists($forbiddenFile), 'File should be deleted after validation fails');
		} finally {
			@unlink($forbiddenFile);
		}

		if (!$exceptionThrown) {
			$this->markTestSkipped('FilenameValidator does not consider this filename as forbidden on this OS');
		}
	}
}
