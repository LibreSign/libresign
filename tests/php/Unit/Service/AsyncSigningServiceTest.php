<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\Db\File as LibreSignFile;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\AsyncSigningService;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\Worker\WorkerActivationService;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\Security\ICredentialsManager;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AsyncSigningServiceTest extends TestCase {
	private AsyncSigningService $service;
	private IJobList&MockObject $jobList;
	private ICredentialsManager&MockObject $credentialsManager;
	private ISecureRandom&MockObject $secureRandom;
	private FileStatusService&MockObject $fileStatusService;
	private IDBConnection&MockObject $db;
	private WorkerActivationService&MockObject $workerActivationService;

	protected function setUp(): void {
		$this->jobList = $this->createMock(IJobList::class);
		$this->credentialsManager = $this->createMock(ICredentialsManager::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->fileStatusService = $this->createMock(FileStatusService::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->workerActivationService = $this->createMock(WorkerActivationService::class);

		$this->service = new AsyncSigningService(
			$this->jobList,
			$this->credentialsManager,
			$this->secureRandom,
			$this->fileStatusService,
			$this->db,
			$this->workerActivationService,
			$this->createMock(LoggerInterface::class),
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
		// For guest users, file must have a userId for the fallback logic
		if ($userId === null) {
			$file->setUserId('');
		}

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
				$this->callback(fn ($credentials) => $credentials['signWithoutPassword'] === $signWithoutPassword
						&& $credentials['password'] === $password
						&& isset($credentials['timestamp']))
			);

		$this->fileStatusService->expects($this->once())
			->method('updateForQueuedJob')
			->with($this->callback(fn ($updatedFile) => $updatedFile->getStatus() === FileStatus::SIGNING_IN_PROGRESS->value))
			->willReturnArgument(0);

		$signatureMethod = 'clickToSign';
		$this->jobList->expects($this->once())
			->method('add')
			->with(
				SignFileJob::class,
				$this->callback(fn ($args) => $args['fileId'] === $fileId
						&& $args['signRequestId'] === $signRequestId
						&& ($userId === null ? $args['userId'] === '' : $args['userId'] === $userId)
						&& $args['credentialsId'] === $expectedCredentialsId
						&& $args['signatureMethod'] === $signatureMethod
						&& isset($args['userUniqueIdentifier'])
						&& isset($args['friendlyName'])
						&& isset($args['visibleElements'])
						&& isset($args['metadata'])
						&& $args['isExternalSigner'] === ($userId === null))
			);

		$result = $this->service->enqueueSigningJob(
			$file,
			$signRequest,
			$user,
			'account:testuser',
			$signWithoutPassword,
			$password,
			$signatureMethod,
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

		$this->fileStatusService->expects($this->once())
			->method('updateForQueuedJob')
			->with($this->callback(fn ($updatedFile) => $updatedFile->getStatus() === FileStatus::SIGNING_IN_PROGRESS->value))
			->willReturnArgument(0);

		$signRequest = new SignRequest();
		$signRequest->setId(1);
		$signRequest->setDisplayName('Test User');

		$this->secureRandom->method('generate')->willReturn('random');
		$this->credentialsManager->method('store');
		$this->jobList->method('add');

		$this->service->enqueueSigningJob(
			$file,
			$signRequest,
			null,
			'email:test@example.com',
			true,
			null,
			null,
			[],
			[]
		);
	}

	public function testMetadataTimestampFormat(): void {
		$file = new LibreSignFile();
		$file->setId(1);
		$file->setMetadata(['old' => 'data']);

		$this->fileStatusService->expects($this->once())
			->method('updateForQueuedJob')
			->with($this->callback(fn ($updatedFile) => $updatedFile->getStatus() === FileStatus::SIGNING_IN_PROGRESS->value))
			->willReturnArgument(0);

		$signRequest = new SignRequest();
		$signRequest->setId(1);
		$signRequest->setDisplayName('User');

		$this->secureRandom->method('generate')->willReturn('random');
		$this->credentialsManager->method('store');
		$this->jobList->method('add');

		$this->service->enqueueSigningJob(
			$file,
			$signRequest,
			null,
			'account:user',
			true,
			null,
			null,
			[],
			[]
		);
	}

	public function testEnqueueRollsBackStatusAndDeletesCredentialsWhenAddingJobFails(): void {
		$file = new LibreSignFile();
		$file->setId(1);
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$file->setMetadata([]);
		$signRequest = new SignRequest();
		$signRequest->setId(1);
		$signRequest->setDisplayName('User');
		$this->secureRandom->method('generate')->willReturn('random');
		$this->credentialsManager->expects($this->once())->method('store');
		$this->credentialsManager->expects($this->once())->method('delete')
			->with('', 'libresign_sign_1_random');
		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->never())->method('commit');
		$this->db->expects($this->once())->method('rollBack');
		$this->fileStatusService->expects($this->once())->method('updateForQueuedJob')
			->willReturnCallback(function (LibreSignFile $current): LibreSignFile {
				return $current;
			});
		$this->fileStatusService->method('discardQueuedJobStatus')
			->willReturnCallback(static function (LibreSignFile $file, int $status, ?array $metadata): void {
				$file->setStatus($status);
				$file->setMetadata($metadata ?? []);
			});
		$this->jobList->expects($this->once())->method('add')
			->willThrowException(new \RuntimeException('Queue unavailable'));

		try {
			$this->service->enqueueSigningJob($file, $signRequest, null, 'account:test', true, null, null, [], []);
			$this->fail('Expected enqueue error');
		} catch (\RuntimeException $e) {
			$this->assertSame('Queue unavailable', $e->getMessage());
		}
		$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $file->getStatus());
		$this->assertSame([], $file->getMetadata());
	}

	public function testEnqueueCommitsOnlyAfterAddingJob(): void {
		$file = new LibreSignFile();
		$file->setId(1);
		$file->setMetadata([]);
		$signRequest = new SignRequest();
		$signRequest->setId(1);
		$signRequest->setDisplayName('User');
		$this->secureRandom->method('generate')->willReturn('random');
		$this->credentialsManager->method('store');
		$events = [];
		$this->db->expects($this->once())->method('beginTransaction')->willReturnCallback(static function () use (&$events): void {
			$events[] = 'begin';
		});
		$this->fileStatusService->method('updateForQueuedJob')->willReturnCallback(static function ($file) use (&$events) {
			$events[] = 'status';
			return $file;
		});
		$this->jobList->expects($this->once())->method('add')->willReturnCallback(static function () use (&$events): void {
			$events[] = 'job';
		});
		$this->fileStatusService->expects($this->once())->method('onQueuedJobCommitted')
			->willReturnCallback(static function () use (&$events): void {
				$events[] = 'invalidate';
			});
		$this->db->expects($this->once())->method('commit')->willReturnCallback(static function () use (&$events): void {
			$events[] = 'commit';
		});

		$this->service->enqueueSigningJob($file, $signRequest, null, 'account:test', true, null, null, [], []);
		$this->assertSame(['begin', 'status', 'job', 'commit', 'invalidate'], $events);
	}
	public function testUnknownTransactionOutcomeKeepsCredentialsAndOriginalFailure(): void {
		$file = new LibreSignFile();
		$file->setId(1);
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$request = new SignRequest();
		$request->setId(1);
		$request->setDisplayName('Test Signer');
		$this->secureRandom->method('generate')->willReturn('random');
		$this->credentialsManager->expects($this->never())->method('delete');
		$this->fileStatusService->expects($this->never())->method('discardQueuedJobStatus');
		$this->jobList->method('add')->willThrowException(new \RuntimeException('Queue failure'));
		$this->db->method('rollBack')->willThrowException(new \RuntimeException('Rollback not confirmed'));

		try {
			$this->service->enqueueSigningJob($file, $request, null, 'account:test', true, null, null, [], []);
			$this->fail('The enqueue error must not be hidden');
		} catch (\RuntimeException $e) {
			$this->assertSame('Queue failure', $e->getMessage());
		}
		$this->assertSame(FileStatus::SIGNING_IN_PROGRESS->value, $file->getStatus());
	}

}
