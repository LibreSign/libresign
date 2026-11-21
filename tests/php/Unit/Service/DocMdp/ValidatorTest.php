<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\DocMdp;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\DocMdp\ConfigService;
use OCA\Libresign\Service\DocMdp\Validator;
use OCA\Libresign\Service\File\Pdf\PdfValidator;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase {
	private IL10N&MockObject $l10n;
	private FileMapper&MockObject $fileMapper;
	private ConfigService&MockObject $configService;
	private PdfValidator&MockObject $pdfValidator;
	private IRootFolder&MockObject $root;

	protected function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->willReturnArgument(0);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->configService = $this->createMock(ConfigService::class);
		$this->pdfValidator = $this->createMock(PdfValidator::class);
		$this->root = $this->createMock(IRootFolder::class);
	}

	private function createValidator(): Validator {
		return new Validator(
			$this->l10n,
			$this->fileMapper,
			$this->configService,
			$this->pdfValidator,
			$this->root,
		);
	}

	#[DataProvider('providerValidateSignersCountFromFile')]
	public function testValidateSignersCountFromFile(
		DocMdpLevel $docMdpLevel,
		?string $signedHash,
		array $signers,
		bool $expectsException,
	): void {
		$file = new File();
		$file->setDocmdpLevel($docMdpLevel->value);
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$file->setSignedHash($signedHash);

		$this->fileMapper
			->method('getByUuid')
			->with('uuid-1')
			->willReturn($file);

		$validator = $this->createValidator();

		if ($expectsException) {
			$this->expectException(LibresignException::class);
		}

		$validator->validateSignersCount([
			'uuid' => 'uuid-1',
			'signers' => $signers,
		]);

		if (!$expectsException) {
			$this->addToAssertionCount(1);
		}
	}

	public static function providerValidateSignersCountFromFile(): array {
		return [
			'signed and no-changes forbids new signer' => [
				DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED,
				'hash',
				[
					['identifyMethods' => [['method' => 'email', 'value' => 'one@example.com']]],
				],
				true,
			],
			'no-changes forbids multiple signers' => [
				DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED,
				'',
				[
					['identifyMethods' => [['method' => 'email', 'value' => 'one@example.com']]],
					['identifyMethods' => [['method' => 'email', 'value' => 'two@example.com']]],
				],
				true,
			],
			'no-changes allows single signer before signing' => [
				DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED,
				'',
				[
					['identifyMethods' => [['method' => 'email', 'value' => 'one@example.com']]],
				],
				false,
			],
			'docmdp level 2 allows multiple signers after signing' => [
				DocMdpLevel::CERTIFIED_FORM_FILLING,
				'hash',
				[
					['identifyMethods' => [['method' => 'email', 'value' => 'one@example.com']]],
					['identifyMethods' => [['method' => 'email', 'value' => 'two@example.com']]],
				],
				false,
			],
			'docmdp level 3 allows multiple signers after signing' => [
				DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS,
				'hash',
				[
					['identifyMethods' => [['method' => 'email', 'value' => 'one@example.com']]],
					['identifyMethods' => [['method' => 'email', 'value' => 'two@example.com']]],
				],
				false,
			],
		];
	}

	public function testValidateSignersCountSkipsWhenNoSigners(): void {
		$this->fileMapper
			->expects($this->never())
			->method('getByUuid');

		$validator = $this->createValidator();
		$validator->validateSignersCount([
			'signers' => [],
		]);
		$this->addToAssertionCount(1);
	}

	public function testValidateSignersCountUsesConfigWhenUuidMissing(): void {
		$this->fileMapper
			->expects($this->never())
			->method('getByUuid');
		$this->configService
			->method('getLevel')
			->willReturn(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED);

		$validator = $this->createValidator();
		$this->expectException(LibresignException::class);

		$validator->validateSignersCount([
			'signers' => [
				['identifyMethods' => [['method' => 'email', 'value' => 'one@example.com']]],
				['identifyMethods' => [['method' => 'email', 'value' => 'two@example.com']]],
			],
		]);
	}

	public function testValidateSignersCountFallsBackToConfigWhenDocMdpLevelZero(): void {
		$file = new File();
		$file->setDocmdpLevel(0);
		$file->setSignedHash('hash');

		$this->fileMapper
			->method('getByUuid')
			->with('uuid-1')
			->willReturn($file);
		$this->configService
			->method('getLevel')
			->willReturn(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED);

		$validator = $this->createValidator();
		$this->expectException(LibresignException::class);

		$validator->validateSignersCount([
			'uuid' => 'uuid-1',
			'signers' => [
				['identifyMethods' => [['method' => 'email', 'value' => 'one@example.com']]],
				['identifyMethods' => [['method' => 'email', 'value' => 'two@example.com']]],
			],
		]);
	}

	#[DataProvider('providerStoredLevelOverridesCurrentConfig')]
	public function testFrozenStoredLevelOverridesCurrentConfig(
		DocMdpLevel $stored,
		DocMdpLevel $current,
		bool $reject,
	): void {
		$file = new File();
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$file->setDocmdpLevelEnum($stored);
		$file->setMetadata(['docmdp_level_captured' => true, 'docmdp_level_frozen' => true]);
		$this->fileMapper->method('getByUuid')->with('uuid-1')->willReturn($file);
		$this->configService->method('getLevel')->willReturn($current);

		if ($reject) {
			$this->expectException(LibresignException::class);
		}
		$this->createValidator()->validateSignersCount([
			'uuid' => 'uuid-1',
			'signers' => [['id' => 1], ['id' => 2]],
		]);
		if (!$reject) {
			$this->addToAssertionCount(1);
		}
	}

	public static function providerStoredLevelOverridesCurrentConfig(): array {
		return [
			'frozen L1 with current L2 rejects' => [DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED, DocMdpLevel::CERTIFIED_FORM_FILLING, true],
			'frozen L2 with current L1 permits' => [DocMdpLevel::CERTIFIED_FORM_FILLING, DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED, false],
			'explicit frozen L0 with current L1 permits' => [DocMdpLevel::NOT_CERTIFIED, DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED, false],
		];
	}

	#[DataProvider('provideExistingDocumentNodeReferences')]
	public function testFrozenLevelIsUsedForExistingDocumentByNodeId(bool $useNodeObject): void {
		$file = new File();
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$file->setDocmdpLevelEnum(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED);
		$this->fileMapper->method('getByNodeId')->with(123)->willReturn($file);
		$this->configService->method('getLevel')->willReturn(DocMdpLevel::CERTIFIED_FORM_FILLING);

		$node = $this->createMock(Node::class);
		$node->method('getId')->willReturn(123);
		$reference = $useNodeObject ? ['fileNode' => $node] : ['nodeId' => 123];

		$this->expectException(LibresignException::class);
		$this->createValidator()->validateSignersCount([
			'file' => $reference,
			'signers' => [['id' => 1], ['id' => 2]],
		]);
	}

	public static function provideExistingDocumentNodeReferences(): array {
		return [
			'nodeId' => [false],
			'fileNode object' => [true],
		];
	}

	public function testAmbiguousExistingDocumentIsRejectedBeforeSignerValidation(): void {
		$this->fileMapper->method('getByNodeId')->with(123)
			->willThrowException(new MultipleObjectsReturnedException());
		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->createValidator()->validateSignersCount([
			'file' => ['nodeId' => 123],
			'signers' => [['id' => 1]],
		]);
	}

	public function testEnvelopeUsesItsMostRestrictiveDocumentLevel(): void {
		$envelope = new File();
		$envelope->setId(50);
		$envelope->setNodeType('envelope');
		$first = new File();
		$first->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$first->setDocmdpLevelEnum(DocMdpLevel::CERTIFIED_FORM_FILLING);
		$second = new File();
		$second->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$second->setDocmdpLevelEnum(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED);
		$this->fileMapper->method('getByUuid')->willReturn($envelope);
		$this->fileMapper->method('getChildrenFiles')->with(50)->willReturn([$first, $second]);
		$this->configService->method('getLevel')->willReturn(DocMdpLevel::CERTIFIED_FORM_FILLING);

		$this->expectException(LibresignException::class);
		$this->createValidator()->validateSignersCount([
			'uuid' => 'envelope',
			'signers' => [['id' => 1], ['id' => 2]],
		]);
	}

	public function testValidatePdfRestrictionsSkipsWhenUnsigned(): void {
		$file = new File();
		$file->setSignedHash(null);

		$this->pdfValidator
			->expects($this->never())
			->method('validate');

		$validator = $this->createValidator();
		$validator->validatePdfRestrictions($file);
	}

	public function testValidatePdfRestrictionsValidatesSignedContent(): void {
		$file = new File();
		$file->setSignedHash('hash');
		$file->setNodeId(123);

		$node = $this->createMock(\OCP\Files\File::class);
		$node->method('getContent')->willReturn('pdf-content');
		$node->method('getName')->willReturn('doc.pdf');

		$this->root
			->method('getById')
			->with(123)
			->willReturn([$node]);

		$this->pdfValidator
			->expects($this->once())
			->method('validate')
			->with('pdf-content', 'doc.pdf');

		$validator = $this->createValidator();
		$validator->validatePdfRestrictions($file);
	}
}
