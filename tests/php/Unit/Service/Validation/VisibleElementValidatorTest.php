<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Validation;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElement;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Enum\NodeType;
use OCA\Libresign\Enum\ParticipantRole;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCA\Libresign\Service\Validation\VisibleElementValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;

final class VisibleElementValidatorTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private VisibleElementValidator $validator;
	private SignRequestMapper $signRequestMapper;
	private FileMapper $fileMapper;
	private FileElementMapper $fileElementMapper;
	private UserElementMapper $userElementMapper;
	private SignerElementsService $signerElementsService;
	private FileInputValidator $fileInputValidator;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->fileElementMapper = $this->createMock(FileElementMapper::class);
		$this->userElementMapper = $this->createMock(UserElementMapper::class);
		$this->signerElementsService = $this->createMock(SignerElementsService::class);
		$this->fileInputValidator = $this->createMock(FileInputValidator::class);
		$this->validator = new VisibleElementValidator(
			$l10n,
			$this->signRequestMapper,
			$this->fileMapper,
			$this->fileElementMapper,
			$this->userElementMapper,
			$this->signerElementsService,
			$this->fileInputValidator,
		);
	}

	#[DataProvider('elementTypeCases')]
	public function testValidatesElementTypes(array $element, bool $valid): void {
		if (!$valid) {
			$this->expectException(LibresignException::class);
		}
		$this->validator->validateElementType($element);
		if ($valid) {
			$this->addToAssertionCount(1);
		}
	}

	public static function elementTypeCases(): array {
		return [
			'signature' => [['type' => 'signature'], true],
			'initial' => [['type' => 'initial'], true],
			'date' => [['type' => 'date'], true],
			'datetime' => [['type' => 'datetime'], true],
			'text' => [['type' => 'text'], true],
			'existing element' => [['elementId' => 3], true],
			'missing type' => [[], false],
			'unsupported type' => [['type' => 'image'], false],
		];
	}

	#[DataProvider('coordinateCases')]
	public function testValidatesCoordinates(array $coordinates, bool $valid): void {
		if (!$valid) {
			$this->expectException(LibresignException::class);
		}
		$this->validator->validateElementCoordinates(['coordinates' => $coordinates]);
		if ($valid) {
			$this->addToAssertionCount(1);
		}
	}

	public static function coordinateCases(): array {
		return [
			'valid' => [['page' => 1, 'left' => 0, 'top' => 10, 'width' => 100, 'height' => 40], true],
			'negative' => [['left' => -1], false],
			'non integer' => [['width' => 10.5], false],
			'page zero' => [['page' => 0], false],
			'page string' => [['page' => '1'], false],
		];
	}

	public function testRejectsElementsWhenFeatureIsDisabled(): void {
		$this->signerElementsService->method('isSignElementsAvailable')->willReturn(false);
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Visible elements are disabled.');
		$this->validator->validateVisibleElements([['type' => 'signature']], FileInputValidator::TYPE_VISIBLE_ELEMENT_USER);
	}

	public function testVisibleElementDelegatesFileValidation(): void {
		$element = ['type' => 'text', 'file' => ['base64' => 'data']];
		$this->fileInputValidator->expects($this->once())->method('validateFile')->with($element, FileInputValidator::TYPE_VISIBLE_ELEMENT_USER);
		$this->validator->validateVisibleElement($element, FileInputValidator::TYPE_VISIBLE_ELEMENT_USER);
	}

	public function testPdfElementMustReferenceSigner(): void {
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Element must be associated with a user');
		$this->validator->validateElementSignRequestId([], FileInputValidator::TYPE_VISIBLE_ELEMENT_PDF);
	}

	public function testValidateElementSignRequestIdRejectsObservers(): void {
		$signRequest = new SignRequest();
		$signRequest->setId(45);
		$signRequest->setFileId(22);
		$signRequest->setStatus(SignRequestStatus::OBSERVING->value);
		$signRequest->setParticipantRole(ParticipantRole::OBSERVER->value);
		$this->signRequestMapper->method('getById')->with(45)->willReturn($signRequest);

		$this->expectExceptionMessage('Observers cannot have visible signature elements');

		$this->validator->validateElementSignRequestId(
			['signRequestId' => 45, 'type' => 'signature'],
			FileInputValidator::TYPE_VISIBLE_ELEMENT_PDF,
		);
	}

	public function testValidateElementSignRequestIdAcceptsSigners(): void {
		$signRequest = new SignRequest();
		$signRequest->setId(46);
		$signRequest->setFileId(22);
		$signRequest->setStatus(SignRequestStatus::ABLE_TO_SIGN->value);
		$signRequest->setParticipantRole(ParticipantRole::SIGNER->value);
		$this->signRequestMapper->method('getById')->with(46)->willReturn($signRequest);

		$actual = $this->validator->validateElementSignRequestId(
			['signRequestId' => 46, 'type' => 'signature'],
			FileInputValidator::TYPE_VISIBLE_ELEMENT_PDF,
		);

		$this->assertNull($actual);
	}

	#[DataProvider('visibleElementFileRelationCases')]
	public function testVisibleElementRequiresMatchingFile(int $existingFileId, bool $allowed): void {
		$file = new File();
		$file->setId(22);
		$this->fileMapper->method('getByUuid')->with('owned-uuid')->willReturn($file);

		$element = new FileElement();
		$element->setId(10);
		$element->setFileId($existingFileId);
		$this->fileElementMapper->method('getById')->with(10)->willReturn($element);

		if (!$allowed) {
			$this->expectException(LibresignException::class);
		}
		$this->validator->validateVisibleElementBelongsToFile(10, 'owned-uuid');
		if ($allowed) {
			$this->addToAssertionCount(1);
		}
	}

	public static function visibleElementFileRelationCases(): array {
		return [
			'same document' => [22, true],
			'other document' => [23, false],
		];
	}

	#[DataProvider('signRequestFileRelationCases')]
	public function testSignRequestRequiresMatchingFile(int $signerFileId, bool $allowed): void {
		$file = new File();
		$file->setId(22);
		$this->fileMapper->method('getByUuid')->with('owned-uuid')->willReturn($file);

		$signRequest = new SignRequest();
		$signRequest->setId(45);
		$signRequest->setFileId($signerFileId);
		$this->signRequestMapper->method('getById')->with(45)->willReturn($signRequest);

		if (!$allowed) {
			$this->expectException(LibresignException::class);
		}
		$this->validator->validateSignRequestBelongsToFile(45, 'owned-uuid');
		if ($allowed) {
			$this->addToAssertionCount(1);
		}
	}

	public static function signRequestFileRelationCases(): array {
		return [
			'same document' => [22, true],
			'other document' => [23, false],
		];
	}

	#[DataProvider('ownerCases')]
	public function testOnlyDocumentOwnerCanModifyVisibleElement(string $fileOwner, bool $allowed): void {
		$file = new File();
		$file->setId(22);
		$file->setUserId($fileOwner);
		$this->fileMapper->method('getById')->with(22)->willReturn($file);

		$signRequest = new SignRequest();
		$signRequest->setId(45);
		$signRequest->setFileId(22);
		$this->signRequestMapper->method('getById')->with(45)->willReturn($signRequest);

		$element = new FileElement();
		$element->setId(10);
		$element->setSignRequestId(45);
		$this->fileElementMapper->method('getById')->with(10)->willReturn($element);

		if (!$allowed) {
			$this->expectException(LibresignException::class);
		}
		$this->validator->validateAuthenticatedUserIsOwnerOfPdfVisibleElement(10, 'owner');
		if ($allowed) {
			$this->addToAssertionCount(1);
		}
	}

	public static function ownerCases(): array {
		return [
			'owner account' => ['owner', true],
			'other account' => ['another-user', false],
		];
	}

	public function testMissingSignRequestIsDenied(): void {
		$file = new File();
		$file->setId(22);
		$this->fileMapper->method('getByUuid')->willReturn($file);
		$this->signRequestMapper->method('getById')->willThrowException(new DoesNotExistException('Signer not found'));

		$this->expectException(LibresignException::class);
		$this->validator->validateSignRequestBelongsToFile(45, 'owned-uuid');
	}

	public function testMissingVisibleElementIsDenied(): void {
		$file = new File();
		$file->setId(22);
		$this->fileMapper->method('getByUuid')->willReturn($file);
		$this->fileElementMapper->method('getById')->willThrowException(new DoesNotExistException('Element not found'));

		$this->expectException(LibresignException::class);
		$this->validator->validateVisibleElementBelongsToFile(10, 'owned-uuid');
	}

	#[DataProvider('relationMethods')]
	public function testUnexpectedLookupFailurePropagates(string $method): void {
		$this->fileMapper->method('getByUuid')->willThrowException(new \RuntimeException('Database unavailable'));
		$this->expectException(\RuntimeException::class);
		$this->validator->{$method}(10, 'owned-uuid');
	}

	public static function relationMethods(): array {
		return [
			'sign request' => ['validateSignRequestBelongsToFile'],
			'visible element' => ['validateVisibleElementBelongsToFile'],
		];
	}

	public function testUnexpectedOwnerLookupErrorPropagates(): void {
		$this->fileElementMapper->method('getById')->willThrowException(new \Error('Unexpected internal failure'));
		$this->expectException(\Error::class);
		$this->validator->validateAuthenticatedUserIsOwnerOfPdfVisibleElement(10, 'owner');
	}

	private function configureSignatureRelation(): SignRequest {
		$signRequest = new SignRequest();
		$signRequest->setId(47);
		$signRequest->setFileId(22);
		$file = new File();
		$file->setNodeTypeEnum(NodeType::FILE);
		$this->fileMapper->method('getById')->with(22)->willReturn($file);
		$fileElement = new FileElement();
		$fileElement->setId(5);
		$fileElement->setSignRequestId(47);
		$fileElement->setType('signature');
		$this->fileElementMapper->method('getById')->with(5)->willReturn($fileElement);
		$this->fileElementMapper->method('getByFileIdAndSignRequestId')->with(22, 47)->willReturn([$fileElement]);
		$this->signerElementsService->method('canCreateSignature')->willReturn(true);
		return $signRequest;
	}

	public function testGuestCanSignWithElementFromCurrentSession(): void {
		$this->signerElementsService->expects($this->once())
			->method('getElementFromSessionByNodeId')
			->with(101)
			->willReturn($this->createMock(\OCP\Files\File::class));

		$this->validator->validateVisibleElementsRelation(
			[['documentElementId' => 5, 'profileNodeId' => 101]],
			$this->configureSignatureRelation(),
			null,
		);
		$this->addToAssertionCount(1);
	}

	public function testGuestCannotSignWithElementOutsideSession(): void {
		$this->signerElementsService->method('getElementFromSessionByNodeId')
			->willThrowException(new DoesNotExistException('Element not found'));

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Field %s does not belong to user');
		$this->validator->validateVisibleElementsRelation(
			[['documentElementId' => 5, 'profileNodeId' => 102]],
			$this->configureSignatureRelation(),
			null,
		);
	}

	#[DataProvider('invalidGuestProfileNodeCases')]
	public function testGuestRejectsNonIntegerNodeWithoutStorageLookup(mixed $profileNodeId): void {
		$this->signerElementsService->expects($this->never())->method('getElementFromSessionByNodeId');

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Field %s must be an integer');
		$this->validator->validateVisibleElementsRelation(
			[['documentElementId' => 5, 'profileNodeId' => $profileNodeId]],
			$this->configureSignatureRelation(),
			null,
		);
	}

	public static function invalidGuestProfileNodeCases(): array {
		return [
			'node string' => ['101'],
			'node null' => [null],
			'node array' => [[101]],
		];
	}

	public function testGuestUnexpectedElementLookupFailureIsNotHidden(): void {
		$this->signerElementsService->method('getElementFromSessionByNodeId')
			->willThrowException(new \RuntimeException('Storage lookup failed'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Storage lookup failed');
		$this->validator->validateVisibleElementsRelation(
			[['documentElementId' => 5, 'profileNodeId' => 101]],
			$this->configureSignatureRelation(),
			null,
		);
	}

	public function testAuthenticatedSignerRejectsUnownedProfileNode(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$this->userElementMapper->method('findOne')
			->willThrowException(new DoesNotExistException('Element not found'));

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Field %s does not belong to user');
		$this->validator->validateVisibleElementsRelation(
			[['documentElementId' => 5, 'profileNodeId' => 101]],
			$this->configureSignatureRelation(),
			$user,
		);
	}

	public function testAuthenticatedSignerUnexpectedLookupFailureIsNotHidden(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$this->userElementMapper->method('findOne')
			->willThrowException(new \RuntimeException('Database lookup failed'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Database lookup failed');
		$this->validator->validateVisibleElementsRelation(
			[['documentElementId' => 5, 'profileNodeId' => 101]],
			$this->configureSignatureRelation(),
			$user,
		);
	}

	public function testAuthenticatedSignerAcceptsOwnedProfileNode(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$this->userElementMapper->expects($this->once())->method('findOne')
			->with(['node_id' => 101, 'user_id' => 'alice'])
			->willReturn(new UserElement());

		$this->validator->validateVisibleElementsRelation(
			[['documentElementId' => 5, 'profileNodeId' => 101]],
			$this->configureSignatureRelation(),
			$user,
		);
		$this->addToAssertionCount(1);
	}
}
