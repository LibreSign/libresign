<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Helper;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ValidateHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cross-component authorization contract for document elements.
 */
final class VisibleElementAuthorizationTest extends TestCase {
	private ValidateHelper $validator;
	private FileMapper $fileMapper;
	private FileElementMapper $elementMapper;
	private SignRequestMapper $signRequestMapper;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$reflection = new \ReflectionClass(ValidateHelper::class);
		$this->validator = $reflection->newInstanceWithoutConstructor();
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->elementMapper = $this->createMock(FileElementMapper::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		foreach ([
			'l10n' => $l10n,
			'fileMapper' => $this->fileMapper,
			'fileElementMapper' => $this->elementMapper,
			'signRequestMapper' => $this->signRequestMapper,
		] as $property => $value) {
			$reflection->getProperty($property)->setValue($this->validator, $value);
		}
	}

	#[DataProvider('fileRelationCases')]
	public function testElementMustBelongToRequestedDocument(int $elementFileId, bool $allowed): void {
		$file = new File();
		$file->setId(100);
		$this->fileMapper->method('getByUuid')->with('document-uuid')->willReturn($file);
		$element = new FileElement();
		$element->setId(7);
		$element->setFileId($elementFileId);
		$this->elementMapper->method('getById')->with(7)->willReturn($element);

		if (!$allowed) {
			$this->expectException(LibresignException::class);
		}
		$this->validator->validateVisibleElementFile(7, 'document-uuid');
		if ($allowed) {
			$this->addToAssertionCount(1);
		}
	}

	public function testFieldIdIsPassedAsLocalizationParameter(): void {
		$file = new File();
		$file->setId(100);
		$this->fileMapper->method('getByUuid')->with('document-uuid')->willReturn($file);
		$element = new FileElement();
		$element->setId(7);
		$element->setFileId(200);
		$this->elementMapper->method('getById')->with(7)->willReturn($element);

		$l10n = $this->createMock(IL10N::class);
		$l10n->expects($this->once())->method('t')
			->with('Field %s does not belong to user', ['7'])->willReturn('Field denied');
		(new \ReflectionProperty(ValidateHelper::class, 'l10n'))->setValue($this->validator, $l10n);

		$this->expectException(LibresignException::class);
		$this->validator->validateVisibleElementFile(7, 'document-uuid');
	}

	public static function fileRelationCases(): array {
		return [
			'same document' => [100, true],
			'unrelated document' => [200, false],
		];
	}

	#[DataProvider('signerRelationCases')]
	public function testSignRequestMustBelongToRequestedDocument(bool $allowed): void {
		$file = new File();
		$file->setId(100);
		$this->fileMapper->method('getByUuid')->with('document-uuid')->willReturn($file);
		$lookup = $this->signRequestMapper->expects($this->once())
			->method('getByFileIdAndSignRequestId')->with(100, 11);
		if ($allowed) {
			$lookup->willReturn(new SignRequest());
		} else {
			$lookup->willThrowException(new DoesNotExistException('No matching signer'));
			$this->expectException(LibresignException::class);
		}
		$this->validator->validateVisibleElementSignRequestFile(11, 'document-uuid');
		if ($allowed) {
			$this->addToAssertionCount(1);
		}
	}

	public static function signerRelationCases(): array {
		return [
			'belonging signer' => [true],
			'unrelated signer' => [false],
		];
	}

	public function testMissingSignRequestIsRejected(): void {
		$file = new File();
		$file->setId(100);
		$this->fileMapper->method('getByUuid')->willReturn($file);
		$this->signRequestMapper->method('getByFileIdAndSignRequestId')
			->willThrowException(new DoesNotExistException('Signer not found'));

		$this->expectException(LibresignException::class);
		$this->validator->validateVisibleElementSignRequestFile(11, 'document-uuid');
	}

	public function testUnexpectedSignerLookupFailurePropagates(): void {
		$file = new File();
		$file->setId(100);
		$this->fileMapper->method('getByUuid')->willReturn($file);
		$this->signRequestMapper->method('getByFileIdAndSignRequestId')
			->willThrowException(new \RuntimeException('Database unavailable'));

		$this->expectException(\RuntimeException::class);
		$this->validator->validateVisibleElementSignRequestFile(11, 'document-uuid');
	}

	public function testUnexpectedElementLookupFailurePropagates(): void {
		$file = new File();
		$file->setId(100);
		$this->fileMapper->method('getByUuid')->willReturn($file);
		$this->elementMapper->method('getById')->willThrowException(new \RuntimeException('Database unavailable'));

		$this->expectException(\RuntimeException::class);
		$this->validator->validateVisibleElementFile(7, 'document-uuid');
	}

	public function testUnexpectedOwnerLookupErrorPropagates(): void {
		$this->elementMapper->method('getById')->willThrowException(new \Error('Unexpected internal failure'));

		$this->expectException(\Error::class);
		$this->validator->validateAuthenticatedUserIsOwnerOfPdfVisibleElement(7, 'owner');
	}

	public function testMissingElementIsRejected(): void {
		$file = new File();
		$file->setId(100);
		$this->fileMapper->method('getByUuid')->willReturn($file);
		$this->elementMapper->method('getById')->willThrowException(new DoesNotExistException('not found'));
		$this->expectException(LibresignException::class);
		$this->validator->validateVisibleElementFile(7, 'document-uuid');
	}
}
