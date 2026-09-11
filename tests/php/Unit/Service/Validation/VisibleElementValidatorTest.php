<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Validation;

use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Enum\ParticipantRole;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCA\Libresign\Service\Validation\VisibleElementValidator;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;

final class VisibleElementValidatorTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private VisibleElementValidator $validator;
	private SignRequestMapper $signRequestMapper;
	private SignerElementsService $signerElementsService;
	private FileInputValidator $fileInputValidator;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->signerElementsService = $this->createMock(SignerElementsService::class);
		$this->fileInputValidator = $this->createMock(FileInputValidator::class);
		$this->validator = new VisibleElementValidator(
			$l10n,
			$this->signRequestMapper,
			$this->createMock(FileMapper::class),
			$this->createMock(FileElementMapper::class),
			$this->createMock(UserElementMapper::class),
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
}
