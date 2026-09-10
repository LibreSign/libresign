<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Validation;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Policy\RequestSignAuthorizationService;
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCA\Libresign\Service\Validation\SigningRequestValidator;
use OCP\IL10N;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;

final class SigningRequestValidatorTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private FileMapper $fileMapper;
	private SignRequestMapper $signRequestMapper;
	private RequestSignAuthorizationService $authorizationService;
	private FileInputValidator $fileInputValidator;
	private SigningRequestValidator $validator;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->authorizationService = $this->createMock(RequestSignAuthorizationService::class);
		$this->fileInputValidator = $this->createMock(FileInputValidator::class);
		$this->validator = new SigningRequestValidator(
			$l10n,
			$this->fileMapper,
			$this->signRequestMapper,
			$this->authorizationService,
			$this->fileInputValidator,
		);
	}

	#[DataProvider('signableStatusCases')]
	public function testFileCanBeSignedOnlyInSignableStatuses(int $status, bool $valid): void {
		$file = new File();
		$file->setStatus($status);
		if (!$valid && $status !== FileStatus::CANCELED->value) {
			$this->fileMapper->method('getTextOfStatus')->with($status)->willReturn('status');
		}
		if (!$valid) {
			$this->expectException(LibresignException::class);
		}
		$this->validator->fileCanBeSigned($file);
		if ($valid) {
			$this->addToAssertionCount(1);
		}
	}

	public static function signableStatusCases(): array {
		return [
			'able to sign' => [FileStatus::ABLE_TO_SIGN->value, true],
			'partially signed' => [FileStatus::PARTIAL_SIGNED->value, true],
			'draft' => [FileStatus::DRAFT->value, false],
			'canceled' => [FileStatus::CANCELED->value, false],
		];
	}

	public function testClosedWorkflowHasSpecificError(): void {
		$file = new File();
		$file->setStatus(FileStatus::CANCELED->value);
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('signing workflow');
		$this->validator->validateWorkflowIsNotClosed($file);
	}

	public function testRequestAuthorizationIsCheckedAtBoundary(): void {
		$user = $this->createMock(IUser::class);
		$this->authorizationService->expects($this->once())->method('canRequestSign')->with($user)->willReturn(false);
		$this->expectException(LibresignException::class);
		$this->validator->canRequestSign($user);
	}

	public function testFileOwnerCanManageExistingFile(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$file = new File();
		$file->setId(15);
		$file->setUserId('alice');
		$this->fileMapper->method('getByUuid')->with('file-uuid')->willReturn($file);

		$this->validator->validateExistingFile(['uuid' => 'file-uuid', 'userManager' => $user]);
		$this->addToAssertionCount(1);
	}

	public function testDifferentUserCannotManageExistingFile(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('bob');
		$file = new File();
		$file->setId(15);
		$file->setUserId('alice');
		$this->fileMapper->method('getByUuid')->with('file-uuid')->willReturn($file);
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('permission');
		$this->validator->validateExistingFile(['uuid' => 'file-uuid', 'userManager' => $user]);
	}

	#[DataProvider('statusChangeCases')]
	public function testValidatesStatusTransitions(int $currentStatus, int $newStatus, bool $valid): void {
		$file = new File();
		$file->setStatus($currentStatus);
		$this->fileMapper->method('getByUuid')->with('uuid')->willReturn($file);
		if (!$valid) {
			$this->expectException(LibresignException::class);
		}
		$this->validator->validateFileStatus(['uuid' => 'uuid', 'status' => $newStatus]);
		if ($valid) {
			$this->addToAssertionCount(1);
		}
	}

	public static function statusChangeCases(): array {
		return [
			'draft to able' => [FileStatus::DRAFT->value, FileStatus::ABLE_TO_SIGN->value, true],
			'able to deleted' => [FileStatus::ABLE_TO_SIGN->value, FileStatus::DELETED->value, true],
			'able cannot restart' => [FileStatus::ABLE_TO_SIGN->value, FileStatus::ABLE_TO_SIGN->value, true],
		];
	}
}
