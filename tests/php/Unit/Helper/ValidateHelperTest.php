<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Helper;

use OCA\Libresign\Db\File;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCA\Libresign\Service\Validation\SignerValidator;
use OCA\Libresign\Service\Validation\SigningRequestValidator;
use OCA\Libresign\Service\Validation\VisibleElementValidator;
use OCP\IUser;

final class ValidateHelperTest extends \OCA\Libresign\Tests\Unit\TestCase {
	public function testDelegatesValidationToFocusedServices(): void {
		$fileInputValidator = $this->createMock(FileInputValidator::class);
		$visibleElementValidator = $this->createMock(VisibleElementValidator::class);
		$signingRequestValidator = $this->createMock(SigningRequestValidator::class);
		$signerValidator = $this->createMock(SignerValidator::class);
		$identityDocumentValidator = $this->createMock(IdentityDocumentValidator::class);

		$helper = new ValidateHelper(
			$fileInputValidator,
			$visibleElementValidator,
			$signingRequestValidator,
			$signerValidator,
			$identityDocumentValidator,
		);

		$user = $this->createMock(IUser::class);
		$file = new File();
		$file->setStatus(1);

		$fileInputValidator->expects($this->once())->method('validateFile')->with(['file' => ['url' => 'https://example.com']], ValidateHelper::TYPE_TO_SIGN, $user);
		$visibleElementValidator->expects($this->once())->method('validateElementType')->with(['type' => 'signature']);
		$signingRequestValidator->expects($this->once())->method('validateWorkflowIsNotClosed')->with($file);
		$signerValidator->expects($this->once())->method('validateUuidFormat')->with('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
		$identityDocumentValidator->expects($this->once())->method('validateFileTypeExists')->with('passport');

		$helper->validateFile(['file' => ['url' => 'https://example.com']], ValidateHelper::TYPE_TO_SIGN, $user);
		$helper->validateElementType(['type' => 'signature']);
		$helper->validateWorkflowIsNotClosed($file);
		$helper->validateUuidFormat('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
		$helper->validateFileTypeExists('passport');
	}

	public function testKeepsPublicFileTypeConstants(): void {
		$this->assertSame(FileInputValidator::TYPE_TO_SIGN, ValidateHelper::TYPE_TO_SIGN);
		$this->assertSame(FileInputValidator::TYPE_VISIBLE_ELEMENT_PDF, ValidateHelper::TYPE_VISIBLE_ELEMENT_PDF);
		$this->assertSame(FileInputValidator::TYPE_VISIBLE_ELEMENT_USER, ValidateHelper::TYPE_VISIBLE_ELEMENT_USER);
		$this->assertSame(FileInputValidator::TYPE_ACCOUNT_DOCUMENT, ValidateHelper::TYPE_ACCOUNT_DOCUMENT);
	}
}
