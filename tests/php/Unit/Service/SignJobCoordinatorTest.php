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
	private SignRequestErrorReporter&MockObject $errorReporter;
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
		$this->errorReporter = $this->createMock(SignRequestErrorReporter::class);
		$this->logger = $this->createMock(LoggerInterface::class);
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

		$this->fileMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (File $updated) {
				return $updated->getStatus() === FileStatus::SIGNING_IN_PROGRESS->value
					&& ($updated->getMetadata()['status_changed_at'] ?? null) !== null;
			}));

		$this->credentialsManager->expects($this->once())
			->method('delete')
			->with('user1', 'cred-1');

		$this->coordinator->runSignSingleFile([
			'fileId' => $file->getId(),
			'signRequestId' => $signRequest->getId(),
			'userId' => 'user1',
			'credentialsId' => 'cred-1',
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

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user2');
		$this->userManager->method('get')->willReturn($user);
		$this->folderService->method('setUserId');

		$this->fileMapper->method('getById')->willReturn($file);
		$this->signRequestMapper->method('getById')->willReturn($signRequest);

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

		$this->fileMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (File $updated) {
				return $updated->getStatus() === FileStatus::SIGNING_IN_PROGRESS->value
					&& ($updated->getMetadata()['status_changed_at'] ?? null) !== null;
			}));

		$this->credentialsManager->expects($this->once())
			->method('delete')
			->with('user2', 'cred-2');

		$this->coordinator->runSignSingleFile([
			'fileId' => $file->getId(),
			'signRequestId' => $signRequest->getId(),
			'userId' => 'user2',
			'credentialsId' => 'cred-2',
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

		$exception = new \Exception('Certificate validation failed', 422);
		$this->signFileService->method('sign')->willThrowException($exception);

		$this->errorReporter->expects($this->once())
			->method('error');

		$this->coordinator->runSignFile([
			'fileId' => $file->getId(),
			'signRequestId' => $signRequest->getId(),
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

		$exception = new \InvalidArgumentException('Invalid parameters', 400);
		$this->signFileService->method('signSingleFile')->willThrowException($exception);

		$this->errorReporter->expects($this->once())
			->method('error');

		$this->fileMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (File $updated) {
				return $updated->getStatus() === FileStatus::SIGNING_IN_PROGRESS->value;
			}));

		$this->coordinator->runSignSingleFile([
			'fileId' => $file->getId(),
			'signRequestId' => $signRequest->getId(),
		]);
	}

	public function testRunSignFileWithoutSignRequestId(): void {
		$this->logger->expects($this->once())
			->method('error');

		$this->coordinator->runSignFile([
			'fileId' => 123,
		]);
	}
}
