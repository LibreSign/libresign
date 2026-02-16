<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\IdDocs;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdDocsPolicyService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\UuidResolverService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class UuidResolverServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private SignFileService&MockObject $signFileService;
	private IdDocsMapper&MockObject $idDocsMapper;
	private IdDocsPolicyService&MockObject $idDocsPolicyService;

	public function setUp(): void {
		parent::setUp();
		$this->signFileService = $this->createMock(SignFileService::class);
		$this->idDocsMapper = $this->createMock(IdDocsMapper::class);
		$this->idDocsPolicyService = $this->createMock(IdDocsPolicyService::class);
	}

	private function getService(): UuidResolverService {
		return new UuidResolverService(
			$this->signFileService,
			$this->idDocsMapper,
			$this->idDocsPolicyService,
		);
	}

	public function testResolveUuidForUserReturnsSignRequest(): void {
		$uuid = 'sign-request-uuid';
		$user = $this->createMock(IUser::class);

		$signRequest = new SignRequest();
		$signRequest->setFileId(1);

		$file = new File();

		$this->signFileService
			->method('getSignRequestByUuid')
			->with($uuid)
			->willReturn($signRequest);

		$this->signFileService
			->method('getFile')
			->with(1)
			->willReturn($file);

		$result = $this->getService()->resolveUuidForUser($uuid, $user);

		$this->assertSame($signRequest, $result['signRequest']);
		$this->assertSame($file, $result['file']);
		$this->assertSame('sign_request', $result['type']);
	}

	public function testResolveUuidForUserReturnsIdDocWhenAuthorized(): void {
		$uuid = 'file-uuid';
		$user = $this->createMock(IUser::class);

		$file = new File();
		$file->setId(1);
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);

		$idDoc = new IdDocs();

		$this->signFileService
			->method('getSignRequestByUuid')
			->with($uuid)
			->willThrowException(new DoesNotExistException(''));

		$this->signFileService
			->method('getFileByUuid')
			->with($uuid)
			->willReturn($file);

		$this->idDocsMapper
			->method('getByFileId')
			->with(1)
			->willReturn($idDoc);

		$this->idDocsPolicyService
			->method('canApproverSignIdDoc')
			->with($user, 1, FileStatus::ABLE_TO_SIGN->value)
			->willReturn(true);

		$result = $this->getService()->resolveUuidForUser($uuid, $user);

		$this->assertNull($result['signRequest']);
		$this->assertSame($file, $result['file']);
		$this->assertSame('id_doc', $result['type']);
	}

	#[DataProvider('provideUnauthorizedIdDocScenarios')]
	public function testResolveUuidForUserThrowsWhenNotAuthorizedForIdDoc(
		bool $hasUser,
		bool $canApprove,
		string $expectedMessage,
	): void {
		$uuid = 'file-uuid';
		$user = $hasUser ? $this->createMock(IUser::class) : null;

		$file = new File();
		$file->setId(1);
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);

		$idDoc = new IdDocs();

		$this->signFileService
			->method('getSignRequestByUuid')
			->willThrowException(new DoesNotExistException(''));

		$this->signFileService
			->method('getFileByUuid')
			->willReturn($file);

		$this->idDocsMapper
			->method('getByFileId')
			->willReturn($idDoc);

		if ($hasUser) {
			$this->idDocsPolicyService
				->method('canApproverSignIdDoc')
				->willReturn($canApprove);
		}

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage($expectedMessage);

		$this->getService()->resolveUuidForUser($uuid, $user);
	}

	public static function provideUnauthorizedIdDocScenarios(): array {
		return [
			'no user provided' => [
				'hasUser' => false,
				'canApprove' => false,
				'expectedMessage' => 'User is not authorized to access this identification document',
			],
			'user cannot approve' => [
				'hasUser' => true,
				'canApprove' => false,
				'expectedMessage' => 'User is not authorized to access this identification document',
			],
		];
	}

	public function testResolveUuidForUserThrowsWhenFileIsNotIdDoc(): void {
		$uuid = 'file-uuid';
		$user = $this->createMock(IUser::class);

		$file = new File();
		$file->setId(1);

		$this->signFileService
			->method('getSignRequestByUuid')
			->willThrowException(new DoesNotExistException(''));

		$this->signFileService
			->method('getFileByUuid')
			->willReturn($file);

		$this->idDocsMapper
			->method('getByFileId')
			->willThrowException(new DoesNotExistException(''));

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('File is not an identification document');

		$this->getService()->resolveUuidForUser($uuid, $user);
	}

	public function testResolveUuidForUserThrowsWhenUuidIsInvalid(): void {
		$uuid = 'invalid-uuid';
		$user = $this->createMock(IUser::class);

		$this->signFileService
			->method('getSignRequestByUuid')
			->willThrowException(new DoesNotExistException(''));

		$this->signFileService
			->method('getFileByUuid')
			->willThrowException(new DoesNotExistException(''));

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Invalid UUID');

		$this->getService()->resolveUuidForUser($uuid, $user);
	}

	public function testResolveUuidPrioritizesSignRequestOverFileUuid(): void {
		$uuid = 'ambiguous-uuid';
		$user = $this->createMock(IUser::class);

		$signRequest = new SignRequest();
		$signRequest->setFileId(1);

		$file = new File();

		$this->signFileService
			->method('getSignRequestByUuid')
			->with($uuid)
			->willReturn($signRequest);

		$this->signFileService
			->method('getFile')
			->willReturn($file);

		$result = $this->getService()->resolveUuidForUser($uuid, $user);

		$this->assertSame('sign_request', $result['type']);
	}
}
