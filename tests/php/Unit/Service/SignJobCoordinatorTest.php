<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\SignJobCoordinator;
use OCA\Libresign\Service\SignRequest\Error\SignRequestErrorReporter;
use OCA\Libresign\Service\SignRequest\ProgressService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ICredentialsManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class SignJobCoordinatorTest extends TestCase {
	private FileMapper&MockObject $fileMapper;
	private SignRequestMapper&MockObject $signRequestMapper;
	private SignFileService&MockObject $signFileService;
	private FolderService&MockObject $folderService;
	private IUserManager&MockObject $userManager;
	private ICredentialsManager&MockObject $credentialsManager;
	private ProgressService&MockObject $progressService;
	private FileService&MockObject $fileService;
	private SignRequestErrorReporter $errorReporter;
	private LoggerInterface&MockObject $logger;
	private SignJobCoordinator $coordinator;

	public function setUp(): void {
		parent::setUp();
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->signFileService = $this->createMock(SignFileService::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->credentialsManager = $this->createMock(ICredentialsManager::class);
		$this->progressService = $this->createMock(ProgressService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->fileService = $this->createMock(FileService::class);
		$this->errorReporter = new SignRequestErrorReporter(
			$this->progressService,
			$this->logger,
		);
		$this->coordinator = new SignJobCoordinator(
			$this->fileMapper,
			$this->signRequestMapper,
			$this->signFileService,
			$this->folderService,
			$this->userManager,
			$this->credentialsManager,
			$this->progressService,
			$this->errorReporter,
			$this->logger,
			$this->fileService,
		);
	}

	public function testRunSignSingleFileMarksInProgressAndDeletesCredentialsOnSuccess(): void {
		$file = new File();
		$file->setId(10);
		$file->setUserId('user1');
		$file->setStatus(FileStatus::DRAFT->value);

		$signRequest = new SignRequest();
		$signRequest->setId(20);
		$signRequest->setFileId($file->getId());
		$signRequest->setUuid('sign-request-uuid-1');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userManager->expects($this->once())
			->method('get')
			->with('user1')
			->willReturn($user);

		$this->folderService->expects($this->once())
			->method('setUserId')
			->with('user1');

		$this->fileMapper->method('getById')->willReturn($file);
		$this->signRequestMapper->method('getById')->willReturn($signRequest);

		$this->progressService->expects($this->once())
			->method('clearSignRequestError')
			->with('sign-request-uuid-1');

		$this->credentialsManager->expects($this->once())
			->method('retrieve')
			->with('user1', 'cred-1')
			->willReturn(['password' => 'pw']);

		$this->signFileService->expects($this->once())
			->method('setPassword')
			->with('pw')
			->willReturnSelf();
		$this->signFileService->expects($this->once())
			->method('setLibreSignFile')
			->with($file)
			->willReturnSelf();
		$this->signFileService->expects($this->once())
			->method('setSignRequest')
			->with($signRequest)
			->willReturnSelf();
		$this->signFileService->expects($this->once())
			->method('setCurrentUser')
			->with($user)
			->willReturnSelf();
		$this->signFileService->expects($this->once())
			->method('storeUserMetadata')
			->with([])
			->willReturnSelf();
		$this->signFileService->expects($this->once())
			->method('setVisibleElements')
			->with([])
			->willReturnSelf();
		$this->signFileService->expects($this->once())
			->method('signSingleFile');

		$this->fileService->expects($this->once())
			->method('update')
			->with($this->callback(function (File $updated) {
				return $updated->getStatus() === FileStatus::SIGNING_IN_PROGRESS->value;
			}))
			->willReturnArgument(0);

		$this->credentialsManager->expects($this->once())
			->method('delete')
			->with('user1', 'cred-1');

		$this->coordinator->runSignSingleFile([
			'fileId' => $file->getId(),
			'signRequestId' => $signRequest->getId(),
			'userId' => 'user1',
			'isExternalSigner' => false,
			'userUniqueIdentifier' => 'account:user1',
			'friendlyName' => 'User 1',
			'signatureMethod' => 'clickToSign',
			'credentialsId' => 'cred-1',
			'metadata' => [],
			'visibleElements' => [],
		]);
	}

	public function testRunSignSingleFileDeletesCredentialsWhenSigningFails(): void {
		$file = new File();
		$file->setId(30);
		$file->setUserId('user2');
		$file->setStatus(FileStatus::DRAFT->value);

		$signRequest = new SignRequest();
		$signRequest->setId(40);
		$signRequest->setFileId($file->getId());
		$signRequest->setUuid('sign-request-uuid-2');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user2');
		$this->userManager->method('get')->willReturn($user);
		$this->folderService->method('setUserId');

		$this->fileMapper->method('getById')->willReturn($file);
		$this->signRequestMapper->method('getById')->willReturn($signRequest);

		$this->progressService->method('clearSignRequestError');

		$this->credentialsManager->method('retrieve')->willReturn(['password' => 'pw2']);

		$this->signFileService->method('setPassword')->willReturnSelf();
		$this->signFileService->method('setLibreSignFile')->willReturnSelf();
		$this->signFileService->method('setSignRequest')->willReturnSelf();
		$this->signFileService->method('setCurrentUser')->willReturnSelf();
		$this->signFileService->method('storeUserMetadata')->willReturnSelf();
		$this->signFileService->method('setVisibleElements')->willReturnSelf();
		$this->signFileService->expects($this->once())
			->method('signSingleFile')
			->willThrowException(new \RuntimeException('failure'));

		$this->fileService->expects($this->once())
			->method('update')
			->with($this->callback(function (File $updated) {
				return $updated->getStatus() === FileStatus::SIGNING_IN_PROGRESS->value;
			}))
			->willReturnArgument(0);

		$this->credentialsManager->expects($this->once())
			->method('delete')
			->with('user2', 'cred-2');

		$this->coordinator->runSignSingleFile([
			'fileId' => $file->getId(),
			'signRequestId' => $signRequest->getId(),
			'userId' => 'user2',
			'isExternalSigner' => false,
			'userUniqueIdentifier' => 'account:user2',
			'friendlyName' => 'User 2',
			'signatureMethod' => 'clickToSign',
			'credentialsId' => 'cred-2',
			'metadata' => [],
			'visibleElements' => [],
		]);
	}

	public function testRunSignFileStoresErrorInProgressService(): void {
		$file = new File();
		$file->setId(10);
		$file->setStatus(FileStatus::DRAFT->value);

		$signRequest = new SignRequest();
		$signRequest->setId(20);
		$signRequest->setFileId($file->getId());
		$signRequest->setUuid('sign-request-uuid');

		$this->fileMapper->method('getById')->willReturn($file);
		$this->signRequestMapper->method('getById')->willReturn($signRequest);

		$this->progressService->expects($this->once())
			->method('clearSignRequestError')
			->with('sign-request-uuid');
		$this->progressService->expects($this->once())
			->method('setSignRequestError')
			->with('sign-request-uuid', $this->arrayHasKey('message'));
		$this->progressService->expects($this->once())
			->method('setFileError')
			->with('sign-request-uuid', $file->getId(), $this->arrayHasKey('message'));

		$exception = new \Exception('Certificate validation failed', 422);
		$this->signFileService->expects($this->once())
			->method('sign')
			->willThrowException($exception);

		$this->coordinator->runSignFile([
			'fileId' => $file->getId(),
			'signRequestId' => $signRequest->getId(),
			'userId' => '',
			'isExternalSigner' => true,
			'userUniqueIdentifier' => 'account:external',
			'friendlyName' => '',
			'signatureMethod' => null,
			'credentialsId' => null,
			'metadata' => [],
			'visibleElements' => [],
		]);
	}

	public function testRunSignSingleFileStoresErrorWhenValidationFails(): void {
		$file = new File();
		$file->setId(50);
		$file->setUserId('user3');
		$file->setStatus(FileStatus::DRAFT->value);

		$signRequest = new SignRequest();
		$signRequest->setId(60);
		$signRequest->setFileId($file->getId());
		$signRequest->setUuid('single-sign-uuid');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user3');
		$this->userManager->method('get')->willReturn($user);
		$this->folderService->method('setUserId');

		$this->fileMapper->method('getById')->willReturn($file);
		$this->signRequestMapper->method('getById')->willReturn($signRequest);

		$this->credentialsManager->method('retrieve')->willReturn(null);

		$this->signFileService->method('setLibreSignFile')->willReturnSelf();
		$this->signFileService->method('setSignRequest')->willReturnSelf();
		$this->signFileService->method('setCurrentUser')->willReturnSelf();
		$this->signFileService->method('storeUserMetadata')->willReturnSelf();
		$this->signFileService->method('setVisibleElements')->willReturnSelf();

		$this->progressService->expects($this->once())
			->method('clearSignRequestError')
			->with('single-sign-uuid');
		$this->progressService->expects($this->once())
			->method('setSignRequestError')
			->with('single-sign-uuid', $this->arrayHasKey('message'));
		$this->progressService->expects($this->once())
			->method('setFileError')
			->with('single-sign-uuid', $file->getId(), $this->arrayHasKey('message'));

		$exception = new \InvalidArgumentException('Invalid parameters', 400);
		$this->signFileService->method('signSingleFile')->willThrowException($exception);

		$this->fileService->expects($this->once())
			->method('update')
			->with($this->callback(function (File $updated) {
				return $updated->getStatus() === FileStatus::SIGNING_IN_PROGRESS->value;
			}))
			->willReturnArgument(0);

		$this->coordinator->runSignSingleFile([
			'fileId' => $file->getId(),
			'signRequestId' => $signRequest->getId(),
			'userId' => 'user3',
			'isExternalSigner' => false,
			'userUniqueIdentifier' => 'account:user3',
			'friendlyName' => 'User 3',
			'signatureMethod' => 'clickToSign',
			'credentialsId' => null,
			'metadata' => [],
			'visibleElements' => [],
		]);
	}

	public function testRunSignFileWithoutSignRequestId(): void {
		$this->logger->expects($this->once())
			->method('error');

		$this->coordinator->runSignFile([
			'fileId' => 123,
			'userId' => '',
			'isExternalSigner' => true,
			'userUniqueIdentifier' => 'account:test',
			'friendlyName' => '',
			'signatureMethod' => null,
			'credentialsId' => null,
			'metadata' => [],
			'visibleElements' => [],
		]);
	}
}
