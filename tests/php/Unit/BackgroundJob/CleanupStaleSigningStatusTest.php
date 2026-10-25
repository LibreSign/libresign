<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\BackgroundJob;

use DateTime;
use OCA\Libresign\BackgroundJob\CleanupStaleSigningStatus;
use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\SigningAttemptGuard;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CleanupStaleSigningStatusTest extends TestCase {
	private FileMapper $files;
	private SignRequestMapper $requests;
	private FileStatusService $statuses;
	private SigningAttemptGuard $attempts;
	private IJobList $jobs;
	private CleanupStaleSigningStatus $cleanup;
	private File $file;
	/** @var array<string, iterable<IJob>> */
	private array $queuedJobs = [];
	private ?\RuntimeException $queueFailure = null;

	protected function setUp(): void {
		$this->files = $this->createMock(FileMapper::class);
		$this->requests = $this->createMock(SignRequestMapper::class);
		$this->statuses = $this->createMock(FileStatusService::class);
		$this->attempts = $this->createMock(SigningAttemptGuard::class);
		$this->jobs = $this->createMock(IJobList::class);
		$this->file = new File();
		$this->file->setId(4);
		$this->file->setStatus(FileStatus::SIGNING_IN_PROGRESS->value);
		$this->file->setMetadata(['status_changed_at' => (new DateTime('-20 minutes'))->format(DateTime::ATOM)]);
		$this->files->method('findStaleSigningInProgress')->willReturn([$this->file]);
		$this->jobs->method('getJobsIterator')->willReturnCallback(function (string $class): iterable {
			if ($this->queueFailure !== null) {
				throw $this->queueFailure;
			}
			return $this->queuedJobs[$class] ?? [];
		});
		$this->cleanup = new CleanupStaleSigningStatus(
			$this->createMock(ITimeFactory::class),
			$this->files, $this->requests, $this->statuses, $this->attempts, $this->jobs,
			$this->createMock(LoggerInterface::class),
		);
	}

	/** @return array<string, array{bool, bool, bool, bool, bool}> */
	public static function recoveryCases(): array {
		return [
			'finished without signed output' => [true, false, false, false, true],
			'finished with signed output' => [true, false, true, false, false],
			'uncertain attempt' => [false, true, false, false, false],
			'legacy attempt without receipt' => [false, false, false, false, false],
			'queued job still active' => [true, false, false, true, false],
		];
	}

	#[DataProvider('recoveryCases')]
	public function testOnlySafeCompletedAttemptsCanBeReset(
		bool $finished,
		bool $uncertain,
		bool $hasSignedOutput,
		bool $hasPendingJob,
		bool $canRetry,
	): void {
		$request = new SignRequest();
		$request->setId(42);
		if ($hasSignedOutput) {
			$request->setSignedHash('signed-pdf-hash');
		}
		$this->requests->method('getByFileId')->willReturn([$request]);
		$this->attempts->method('hasFinishedAttempt')->willReturn($finished);
		$this->attempts->method('hasUncertainAttempt')->willReturn($uncertain);
		if ($hasPendingJob) {
			$job = $this->createMock(IJob::class);
			$job->method('getArgument')->willReturn(['fileId' => 4]);
			$this->queuedJobs[SignFileJob::class] = [$job];
		}
		$this->cleanup->run(null);
		$this->assertSame($canRetry ? FileStatus::ABLE_TO_SIGN->value : FileStatus::SIGNING_IN_PROGRESS->value, $this->file->getStatus());
	}

	public function testQueueFailureIsReportedToNextcloud(): void {
		$this->queueFailure = new \RuntimeException('Unable to read the queue');
		$this->expectException(\RuntimeException::class);
		$this->cleanup->run(null);
	}

	public function testDatabaseFailureIsNotReportedAsSuccessfulCleanup(): void {
		$this->files->method('findStaleSigningInProgress')->willThrowException(new \RuntimeException('Storage unavailable'));
		$this->expectException(\RuntimeException::class);
		$this->cleanup->run(null);
	}

	public function testRecoveryWriteFailureIsReportedToNextcloud(): void {
		$request = new SignRequest();
		$request->setId(42);
		$this->requests->method('getByFileId')->willReturn([$request]);
		$this->attempts->method('hasFinishedAttempt')->willReturn(true);
		$this->statuses->method('update')->willThrowException(new \RuntimeException('Unable to persist status'));
		$this->expectException(\RuntimeException::class);
		$this->cleanup->run(null);
	}

	public function testCleanupDoesNotResetWhenQueueHasInvalidArguments(): void {
		$job = $this->createMock(IJob::class);
		$job->method('getArgument')->willReturn(['fileId' => 'not-an-id']);
		$this->queuedJobs[SignFileJob::class] = [$job];
		$this->cleanup->run(null);
		$this->assertSame(FileStatus::SIGNING_IN_PROGRESS->value, $this->file->getStatus());
	}

	public function testLargeQueueDoesNotTriggerUnsafeReset(): void {
		$job = $this->createMock(IJob::class);
		$job->method('getArgument')->willReturn(['fileId' => 900]);
		$this->queuedJobs[SignFileJob::class] = (static function () use ($job): iterable {
			for ($i = 0; $i <= 5000; $i++) {
				yield $job;
			}
		})();
		$this->cleanup->run(null);
		$this->assertSame(FileStatus::SIGNING_IN_PROGRESS->value, $this->file->getStatus());
	}

	public function testChildDoesNotResetWhileParentSigningJobIsPending(): void {
		$this->file->setParentFileId(99);
		$pending = $this->createMock(IJob::class);
		$pending->method('getArgument')->willReturn(['fileId' => 99]);
		$this->queuedJobs[SignFileJob::class] = [$pending];
		$this->statuses->expects($this->never())->method('update');
		$this->cleanup->run(null);
		$this->assertSame(FileStatus::SIGNING_IN_PROGRESS->value, $this->file->getStatus());
	}

	public function testEnvelopeDoesNotResetWhileChildJobIsPending(): void {
		$this->file->setNodeType('envelope');
		$child = new File();
		$child->setId(9);
		$this->files->method('getChildrenFiles')->with(4)->willReturn([$child]);
		$pending = $this->createMock(IJob::class);
		$pending->method('getArgument')->willReturn(['fileId' => 9]);
		$this->queuedJobs[SignSingleFileJob::class] = [$pending];
		$this->statuses->expects($this->never())->method('update');
		$this->statuses->expects($this->never())->method('propagateStatusToParent');
		$this->cleanup->run(null);
		$this->assertSame(FileStatus::SIGNING_IN_PROGRESS->value, $this->file->getStatus());
	}
}
