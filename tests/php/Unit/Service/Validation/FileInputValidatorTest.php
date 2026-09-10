<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Validation;

use OCA\Libresign\Db\File as LibresignFile;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCP\Files\File;
use OCP\Files\IMimeTypeDetector;
use OCP\IL10N;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;

final class FileInputValidatorTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IL10N $l10n;
	private SignRequestMapper $signRequestMapper;
	private FileMapper $fileMapper;
	private IMimeTypeDetector $mimeTypeDetector;
	private FolderService $folderService;
	private FileInputValidator $validator;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->validator = new FileInputValidator(
			$this->l10n,
			$this->signRequestMapper,
			$this->fileMapper,
			$this->mimeTypeDetector,
			$this->folderService,
		);
	}

	#[DataProvider('mimeTypeCases')]
	public function testValidatesMimeTypeForFileRole(string $mimeType, int $type, bool $valid): void {
		if (!$valid) {
			$this->expectException(LibresignException::class);
		}
		$this->validator->validateMimeTypeAcceptedByMime($mimeType, $type);
		if ($valid) {
			$this->addToAssertionCount(1);
		}
	}

	public static function mimeTypeCases(): array {
		return [
			'pdf document' => ['application/pdf', FileInputValidator::TYPE_TO_SIGN, true],
			'png is not document' => ['image/png', FileInputValidator::TYPE_TO_SIGN, false],
			'png visible element' => ['image/png', FileInputValidator::TYPE_VISIBLE_ELEMENT_USER, true],
			'pdf is not visible element' => ['application/pdf', FileInputValidator::TYPE_VISIBLE_ELEMENT_PDF, false],
			'account document keeps unrestricted mime behavior' => ['image/jpeg', FileInputValidator::TYPE_ACCOUNT_DOCUMENT, true],
		];
	}

	public function testNodeValidationUsesReadableFilesystemBoundary(): void {
		$node = $this->createMock(File::class);
		$node->method('getMimeType')->willReturn('application/pdf');
		$this->folderService
			->expects($this->exactly(2))
			->method('getReadableNodeById')
			->with('alice', 42)
			->willReturn($node);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');

		$this->validator->validateFile([
			'file' => ['nodeId' => 42],
			'userManager' => $user,
		]);
	}

	public function testUnreadableNodeIsRejected(): void {
		$this->folderService->method('getReadableNodeById')->with('alice', 42)->willReturn(null);
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Invalid fileID');
		$this->validator->validateIfNodeIdExists(42, 'alice');
	}

	public function testStoredFileOwnerIsUsedWhenUserIdIsNotProvided(): void {
		$file = new LibresignFile();
		$file->setUserId('owner');
		$this->fileMapper->method('getByNodeId')->with(10)->willReturn($file);
		$this->folderService->expects($this->once())->method('getReadableNodeById')->with('owner', 10)->willReturn($this->createMock(File::class));

		$this->validator->validateIfNodeIdExists(10);
	}

	#[DataProvider('base64Cases')]
	public function testValidatesBase64Content(string $payload, int $type, string $detectedMime, bool $valid): void {
		$this->mimeTypeDetector->method('detectString')->willReturn($detectedMime);
		if (!$valid) {
			$this->expectException(LibresignException::class);
		}
		$this->validator->validateBase64($payload, $type);
		if ($valid) {
			$this->addToAssertionCount(1);
		}
	}

	public static function base64Cases(): array {
		$pdf = base64_encode('%PDF-test');
		$png = base64_encode("\x89PNG-test");
		return [
			'pdf document' => [$pdf, FileInputValidator::TYPE_TO_SIGN, 'application/pdf', true],
			'wrong document mime' => [$png, FileInputValidator::TYPE_TO_SIGN, 'image/png', false],
			'png visible element' => [$png, FileInputValidator::TYPE_VISIBLE_ELEMENT_USER, 'image/png', true],
			'wrong visible mime' => [$pdf, FileInputValidator::TYPE_VISIBLE_ELEMENT_USER, 'application/pdf', false],
			'invalid base64' => ['not base64!', FileInputValidator::TYPE_TO_SIGN, 'application/pdf', false],
		];
	}

	public function testRejectsDuplicateSignRequest(): void {
		$this->signRequestMapper->method('getByNodeId')->with(7)->willReturn($this->createMock(\OCA\Libresign\Db\SignRequest::class));
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Already asked to sign this document');
		$this->validator->validateNotRequestedSign(7);
	}
}
