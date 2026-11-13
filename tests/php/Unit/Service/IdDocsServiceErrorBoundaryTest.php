<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\IdDocsUploadValidationException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdDocsService;
use OCA\Libresign\Service\RequestSignatureService;
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

class IdDocsServiceErrorBoundaryTest extends TestCase {
	private function serviceWithValidationFailure(\Exception $exception): IdDocsService {
		$l10n = $this->createMock(IL10N::class);
		$fileTypeMapper = $this->createMock(FileTypeMapper::class);
		$fileTypeMapper->method('getTypes')->willReturn(['IDENTIFICATION' => []]);
		$fileInputValidator = $this->createMock(FileInputValidator::class);
		$fileInputValidator->method('validateNewFile')->willThrowException($exception);
		return new IdDocsService(
			$l10n,
			$fileTypeMapper,
			$fileInputValidator,
			$this->createMock(IdentityDocumentValidator::class),
			$this->createMock(RequestSignatureService::class),
			$this->createMock(IdDocsMapper::class),
			$this->createMock(FileMapper::class),
			$this->createMock(SignRequestMapper::class),
			$this->createMock(IdentifyMethodMapper::class),
			$this->createMock(ITimeFactory::class),
			$this->createMock(IAppConfig::class),
			$this->createMock(IUserManager::class),
		);
	}

	public function testExpectedValidationExceptionRetainsTheFileIndex(): void {
		$service = $this->serviceWithValidationFailure(new LibresignException('Invalid fileID.'));
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('synthetic-user');

		try {
			$service->validateIdDocs([['type' => 'IDENTIFICATION', 'file' => ['nodeId' => 12]]], $user);
			$this->fail('Expected a file-indexed validation error');
		} catch (IdDocsUploadValidationException $exception) {
			$this->assertSame(
				['type' => 'danger', 'file' => 0, 'message' => 'Invalid fileID.'],
				json_decode($exception->getMessage(), true),
			);
		}
	}

	public function testUnexpectedInfrastructureExceptionIsNotReclassifiedAsValidation(): void {
		$service = $this->serviceWithValidationFailure(new \RuntimeException('internal-canary-123'));
		$user = $this->createMock(IUser::class);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('internal-canary-123');
		$service->validateIdDocs([['type' => 'IDENTIFICATION', 'file' => ['nodeId' => 12]]], $user);
	}
}
