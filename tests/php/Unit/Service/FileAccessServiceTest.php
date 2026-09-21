<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\FileAccessService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SignFileService;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class FileAccessServiceTest extends TestCase {
	private FileMapper&MockObject $fileMapper;
	private SignRequestMapper&MockObject $signRequestMapper;
	private SignFileService&MockObject $signFileService;
	private IUserSession&MockObject $userSession;
	private FileAccessService $service;

	protected function setUp(): void {
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->signFileService = $this->createMock(SignFileService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->service = new FileAccessService(
			$this->fileMapper,
			$this->signRequestMapper,
			$this->signFileService,
			$this->userSession,
		);
	}

	public function testOwnerCanAccessFileByIdWithoutSignerChecks(): void {
		$user = $this->mockUser('owner');
		$file = $this->createFileEntity(fileId: 10, ownerId: 'owner');

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with(10)
			->willReturn($file);
		$this->signRequestMapper->expects($this->never())->method('getByFileId');
		$this->signFileService->expects($this->never())->method('getSignRequestToSign');

		$this->assertTrue($this->service->userCanAccessFileById(10, $user));
	}

	public function testSignedSignerCanAccessFileByIdEvenAfterSigning(): void {
		$user = $this->mockUser('signer');
		$file = $this->createFileEntity(fileId: 20, ownerId: 'owner', status: FileStatus::SIGNED->value);
		$signRequest = $this->createSignRequest(signRequestId: 501, fileId: 20, status: SignRequestStatus::SIGNED->value);
		$signRequest->setSigned(new \DateTime('2026-01-28T23:58:51+00:00'));

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with(20)
			->willReturn($file);
		$this->signRequestMapper->expects($this->once())
			->method('getByFileId')
			->with(20)
			->willReturn([$signRequest]);
		$this->signRequestMapper->expects($this->once())
			->method('getIdentifyMethodsFromSigners')
			->with([$signRequest])
			->willReturn([
				501 => [
					IdentifyMethodService::IDENTIFY_ACCOUNT => $this->createIdentifyMethod(501, IdentifyMethodService::IDENTIFY_ACCOUNT, 'signer'),
				],
			]);
		$this->signFileService->expects($this->never())->method('getSignRequestToSign');

		$this->assertTrue($this->service->userCanAccessFileById(20, $user));
	}

	public function testEmailSignerCanAccessFileByIdWithoutAccountIdentity(): void {
		$user = $this->mockUser('signer', 'signer@example.test');
		$file = $this->createFileEntity(fileId: 21, ownerId: 'owner');
		$signRequest = $this->createSignRequest(signRequestId: 502, fileId: 21, status: SignRequestStatus::ABLE_TO_SIGN->value);

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with(21)
			->willReturn($file);
		$this->signRequestMapper->expects($this->once())
			->method('getByFileId')
			->with(21)
			->willReturn([$signRequest]);
		$this->signRequestMapper->expects($this->once())
			->method('getIdentifyMethodsFromSigners')
			->with([$signRequest])
			->willReturn([
				502 => [
					IdentifyMethodService::IDENTIFY_EMAIL => $this->createIdentifyMethod(502, IdentifyMethodService::IDENTIFY_EMAIL, 'signer@example.test'),
				],
			]);
		$this->signFileService->expects($this->never())->method('getSignRequestToSign');

		$this->assertTrue($this->service->userCanAccessFileById(21, $user));
	}

	public function testAssociatedSignerCanAccessFileByNodeIdEvenWhenSigningIsBlocked(): void {
		$user = $this->mockUser('future-signer');
		$file = $this->createFileEntity(fileId: 22, ownerId: 'owner', nodeId: 99, status: FileStatus::PARTIAL_SIGNED->value);
		$signRequest = $this->createSignRequest(signRequestId: 503, fileId: 22, status: SignRequestStatus::ABLE_TO_SIGN->value);

		$this->fileMapper->expects($this->once())
			->method('getByNodeId')
			->with(99)
			->willReturn($file);
		$this->signRequestMapper->expects($this->once())
			->method('getByFileId')
			->with(22)
			->willReturn([$signRequest]);
		$this->signRequestMapper->expects($this->once())
			->method('getIdentifyMethodsFromSigners')
			->with([$signRequest])
			->willReturn([
				503 => [
					IdentifyMethodService::IDENTIFY_ACCOUNT => $this->createIdentifyMethod(503, IdentifyMethodService::IDENTIFY_ACCOUNT, 'future-signer'),
				],
			]);
		$this->signFileService->expects($this->never())->method('getSignRequestToSign');

		$this->assertTrue($this->service->userCanAccessFileByNodeId(99, $user));
	}

	public function testDraftSignerCannotAccessFileBeforeRequestIsReady(): void {
		$user = $this->mockUser('signer');
		$file = $this->createFileEntity(fileId: 23, ownerId: 'owner', status: FileStatus::DRAFT->value);

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with(23)
			->willReturn($file);
		$this->signRequestMapper->expects($this->never())->method('getByFileId');
		$this->signFileService->expects($this->once())
			->method('getSignRequestToSign')
			->with($file, null, $user)
			->willThrowException(new \RuntimeException('not ready'));

		$this->assertFalse($this->service->userCanAccessFileById(23, $user));
	}

	public function testOutsiderCannotAccessFileById(): void {
		$user = $this->mockUser('outsider');
		$file = $this->createFileEntity(fileId: 30, ownerId: 'owner');
		$signRequest = $this->createSignRequest(signRequestId: 504, fileId: 30, status: SignRequestStatus::ABLE_TO_SIGN->value);

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with(30)
			->willReturn($file);
		$this->signRequestMapper->expects($this->once())
			->method('getByFileId')
			->with(30)
			->willReturn([$signRequest]);
		$this->signRequestMapper->expects($this->once())
			->method('getIdentifyMethodsFromSigners')
			->with([$signRequest])
			->willReturn([
				504 => [
					IdentifyMethodService::IDENTIFY_ACCOUNT => $this->createIdentifyMethod(504, IdentifyMethodService::IDENTIFY_ACCOUNT, 'another-signer'),
				],
			]);
		$this->signFileService->expects($this->once())
			->method('getSignRequestToSign')
			->with($file, null, $user)
			->willThrowException(new \RuntimeException('not a signer'));

		$this->assertFalse($this->service->userCanAccessFileById(30, $user));
	}

	#[DataProvider('provideMissingUserScenarios')]
	public function testUserCanAccessFileReturnsFalseWithoutResolvedUser(
		string $method,
		string $mapperMethod,
		int $identifier,
	): void {
		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn(null);
		$this->fileMapper->expects($this->never())->method($mapperMethod);

		$this->assertFalse($this->service->{$method}($identifier));
	}

	public static function provideMissingUserScenarios(): array {
		return [
			'file id without user' => ['userCanAccessFileById', 'getById', 40],
			'node id without user' => ['userCanAccessFileByNodeId', 'getByNodeId', 41],
		];
	}

	private function mockUser(string $uid, ?string $email = null): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getEMailAddress')->willReturn($email);
		return $user;
	}

	private function createFileEntity(
		int $fileId,
		string $ownerId,
		?int $nodeId = null,
		int $status = FileStatus::ABLE_TO_SIGN->value,
	): FileEntity {
		$file = new FileEntity();
		$file->setId($fileId);
		$file->setNodeId($nodeId);
		$file->setUserId($ownerId);
		$file->setStatus($status);
		return $file;
	}

	private function createSignRequest(int $signRequestId, int $fileId, int $status): SignRequest {
		$signRequest = new SignRequest();
		$signRequest->setId($signRequestId);
		$signRequest->setFileId($fileId);
		$signRequest->setStatus($status);
		return $signRequest;
	}

	private function createIdentifyMethod(int $signRequestId, string $key, string $value): IdentifyMethod {
		$identifyMethod = new IdentifyMethod();
		$identifyMethod->setSignRequestId($signRequestId);
		$identifyMethod->setIdentifierKey($key);
		$identifyMethod->setIdentifierValue($value);
		return $identifyMethod;
	}
}
