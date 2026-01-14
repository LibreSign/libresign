<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\Db\File as LibreSignFile;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\AsyncSigningService;
use OCA\Libresign\Service\WorkerHealthService;
use OCP\BackgroundJob\IJobList;
use OCP\IUser;
use OCP\Security\ICredentialsManager;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AsyncSigningServiceTest extends TestCase {
	private AsyncSigningService $service;
	private IJobList&MockObject $jobList;
	private ICredentialsManager&MockObject $credentialsManager;
	private ISecureRandom&MockObject $secureRandom;
	private FileMapper&MockObject $fileMapper;
	private WorkerHealthService&MockObject $workerHealthService;

	protected function setUp(): void {
		$this->jobList = $this->createMock(IJobList::class);
		$this->credentialsManager = $this->createMock(ICredentialsManager::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->workerHealthService = $this->createMock(WorkerHealthService::class);

		$this->service = new AsyncSigningService(
			$this->jobList,
			$this->credentialsManager,
			$this->secureRandom,
			$this->fileMapper,
			$this->workerHealthService
		);
	}

	public static function signingJobProvider(): array {
		return [
			'password-based signing' => [
				'signWithoutPassword' => false,
				'password' => 'SecurePassword123',
				'userId' => 'testuser',
				'fileId' => 42,
				'signRequestId' => 10,
			],
			'passwordless signing' => [
				'signWithoutPassword' => true,
				'password' => null,
				'userId' => 'admin',
				'fileId' => 100,
				'signRequestId' => 50,
			],
			'passwordless for guest user' => [
				'signWithoutPassword' => true,
				'password' => null,
				'userId' => null,
				'fileId' => 200,
				'signRequestId' => 75,
			],
			'password signing for guest' => [
				'signWithoutPassword' => false,
				'password' => 'GuestPass456',
				'userId' => null,
				'fileId' => 150,
				'signRequestId' => 60,
			],
		];
	}

	#[DataProvider('signingJobProvider')]
	public function testEnqueueSigningJob(
		bool $signWithoutPassword,
		?string $password,
		?string $userId,
		int $fileId,
		int $signRequestId,
	): void {
		$file = new LibreSignFile();
		$file->setId($fileId);
		$file->setMetadata(['existing' => 'data']);

		$signRequest = new SignRequest();
		$signRequest->setId($signRequestId);
		$signRequest->setDisplayName('John Doe');

		$user = null;
		if ($userId !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($userId);
		}

		$generatedCredId = 'random_cred_id';
		$this->secureRandom->expects($this->once())
			->method('generate')
			->with(16, ISecureRandom::CHAR_ALPHANUMERIC)
			->willReturn($generatedCredId);

		$expectedCredentialsId = 'libresign_sign_' . $signRequestId . '_' . $generatedCredId;

		$this->credentialsManager->expects($this->once())
			->method('store')
			->with(
				$userId ?? '',
				$expectedCredentialsId,
				$this->callback(function ($credentials) use ($signWithoutPassword, $password) {
					return $credentials['signWithoutPassword'] === $signWithoutPassword
						&& $credentials['password'] === $password
						&& isset($credentials['timestamp']);
				})
			);

		$this->fileMapper->expects($this->once())
			->method('update')
			->with($this->callback(function ($updatedFile) {
				$metadata = $updatedFile->getMetadata();
				return isset($metadata['status_changed_at']);
			}));

		$this->jobList->expects($this->once())
			->method('add')
			->with(
				SignFileJob::class,
				$this->callback(function ($args) use ($fileId, $signRequestId, $userId, $expectedCredentialsId) {
					return $args['fileId'] === $fileId
						&& $args['signRequestId'] === $signRequestId
						&& $args['userId'] === $userId
						&& $args['credentialsId'] === $expectedCredentialsId
						&& isset($args['userUniqueIdentifier'])
						&& isset($args['friendlyName'])
						&& isset($args['visibleElements'])
						&& isset($args['metadata']);
				})
			);

		$this->workerHealthService->expects($this->once())
			->method('ensureWorkerRunning');

		$result = $this->service->enqueueSigningJob(
			$file,
			$signRequest,
			$user,
			'account:testuser',
			$signWithoutPassword,
			$password,
			[],
			['user-agent' => 'TestAgent']
		);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('credentialsId', $result);
		$this->assertArrayHasKey('jobAdded', $result);
		$this->assertSame($expectedCredentialsId, $result['credentialsId']);
		$this->assertTrue($result['jobAdded']);
	}

	public function testFileStatusUpdatedToSigningInProgress(): void {
		$file = new LibreSignFile();
		$file->setId(1);
		$file->setMetadata([]);

		$this->fileMapper->expects($this->once())
			->method('update')
			->with($this->callback(function ($updatedFile) {
				return $updatedFile->getStatusEnum() === FileStatus::SIGNING_IN_PROGRESS
					&& isset($updatedFile->getMetadata()['status_changed_at']);
			}));

		$signRequest = new SignRequest();
		$signRequest->setId(1);
		$signRequest->setDisplayName('Test User');

		$this->secureRandom->method('generate')->willReturn('random');
		$this->credentialsManager->method('store');
		$this->jobList->method('add');
		$this->workerHealthService->method('ensureWorkerRunning');

		$this->service->enqueueSigningJob(
			$file,
			$signRequest,
			null,
			'email:test@example.com',
			true,
			null,
			[],
			[]
		);
	}

	public function testMetadataTimestampFormat(): void {
		$file = new LibreSignFile();
		$file->setId(1);
		$file->setMetadata(['old' => 'data']);

		$this->fileMapper->expects($this->once())
			->method('update')
			->with($this->callback(function ($updatedFile) {
				$metadata = $updatedFile->getMetadata();
				if (!isset($metadata['status_changed_at'])) {
					return false;
				}
				$timestamp = $metadata['status_changed_at'];
				$parsed = \DateTime::createFromFormat(\DateTimeInterface::ATOM, $timestamp);
				return $parsed !== false && $parsed->format(\DateTimeInterface::ATOM) === $timestamp;
			}));

		$signRequest = new SignRequest();
		$signRequest->setId(1);
		$signRequest->setDisplayName('User');

		$this->secureRandom->method('generate')->willReturn('random');
		$this->credentialsManager->method('store');
		$this->jobList->method('add');
		$this->workerHealthService->method('ensureWorkerRunning');

		$this->service->enqueueSigningJob(
			$file,
			$signRequest,
			null,
			'account:user',
			true,
			null,
			[],
			[]
		);
	}

	public function testWorkerEnsuredAfterEnqueue(): void {
		$file = new LibreSignFile();
		$file->setId(1);
		$file->setMetadata([]);

		$signRequest = new SignRequest();
		$signRequest->setId(1);
		$signRequest->setDisplayName('User');

		$this->secureRandom->method('generate')->willReturn('random');
		$this->fileMapper->method('update');
		$this->credentialsManager->method('store');

		$jobAdded = false;
		$this->jobList->expects($this->once())
			->method('add')
			->willReturnCallback(function () use (&$jobAdded) {
				$jobAdded = true;
			});

		$this->workerHealthService->expects($this->once())
			->method('ensureWorkerRunning')
			->willReturnCallback(function () use (&$jobAdded) {
				$this->assertTrue($jobAdded, 'Worker should be ensured AFTER job is added');
				return true;
			});

		$this->service->enqueueSigningJob(
			$file,
			$signRequest,
			null,
			'account:test',
			true,
			null,
			[],
			[]
		);
	}
}
