<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Validation;

use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\IdDocsPolicyService;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;

final class IdentityDocumentValidatorTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IdDocsMapper $idDocsMapper;
	private FileTypeMapper $fileTypeMapper;
	private IdDocsPolicyService $policyService;
	private IUserManager $userManager;
	private IdentityDocumentValidator $validator;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->idDocsMapper = $this->createMock(IdDocsMapper::class);
		$this->fileTypeMapper = $this->createMock(FileTypeMapper::class);
		$this->policyService = $this->createMock(IdDocsPolicyService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->validator = new IdentityDocumentValidator(
			$l10n,
			$this->idDocsMapper,
			$this->fileTypeMapper,
			$this->policyService,
			$this->userManager,
		);
	}

	#[DataProvider('mailCases')]
	public function testValidatesSignerEmail(array $data, bool $valid): void {
		if (!$valid) {
			$this->expectException(LibresignException::class);
		}
		$this->validator->haveValidMail($data);
		if ($valid) {
			$this->addToAssertionCount(1);
		}
	}

	public static function mailCases(): array {
		return [
			'valid' => [['email' => 'alice@example.com'], true],
			'invalid' => [['email' => 'invalid'], false],
			'missing' => [[], false],
		];
	}

	public function testUidCanProvideEmailFromAccount(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('alice@example.com');
		$this->userManager->method('get')->with('alice')->willReturn($user);
		$this->validator->haveValidMail(['uid' => 'alice']);
		$this->addToAssertionCount(1);
	}

	public function testRejectsAccountWithoutEmail(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn(null);
		$this->userManager->method('get')->with('alice')->willReturn($user);
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('has no email address');
		$this->validator->haveValidMail(['uid' => 'alice']);
	}

	public function testValidatesConfiguredIdentityDocumentType(): void {
		$this->fileTypeMapper->method('getTypes')->willReturn(['passport' => []]);
		$this->validator->validateFileTypeExists('passport');
		$this->addToAssertionCount(1);
	}

	public function testRejectsUnknownIdentityDocumentType(): void {
		$this->fileTypeMapper->method('getTypes')->willReturn(['passport' => []]);
		$this->expectException(LibresignException::class);
		$this->validator->validateFileTypeExists('driver-license');
	}

	#[DataProvider('identityDocumentStatusCases')]
	public function testSigningStatusRules(int $status, bool $valid): void {
		if (!$valid) {
			$this->expectException(LibresignException::class);
		}
		$this->validator->canSignWithIdentificationDocumentStatus(null, $status);
		if ($valid) {
			$this->addToAssertionCount(1);
		}
	}

	public static function identityDocumentStatusCases(): array {
		return [
			'disabled' => [FileService::IDENTIFICATION_DOCUMENTS_DISABLED, true],
			'approved' => [FileService::IDENTIFICATION_DOCUMENTS_APPROVED, true],
			'pending' => [FileService::IDENTIFICATION_DOCUMENTS_WAITING_APPROVAL, false],
		];
	}

	public function testApproverBypassesIdentityDocumentStatus(): void {
		$user = $this->createMock(IUser::class);
		$this->policyService->method('userCanApproveValidationDocuments')->with($user, false)->willReturn(true);
		$this->validator->canSignWithIdentificationDocumentStatus($user, FileService::IDENTIFICATION_DOCUMENTS_WAITING_APPROVAL);
		$this->addToAssertionCount(1);
	}
}
