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
		$this->jobs->method('getJobsIterator')->willReturnCallback(static fn (): array => []);
		$this->cleanup = new CleanupStaleSigningStatus(
			$this->createMock(ITimeFactory::class),
			$this->files, $this->requests, $this->statuses, $this->attempts, $this->jobs,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testDoesNotResetStatusWhileFileHasPendingJob(): void {
		$pending = $this->createMock(IJob::class);
		$pending->method('getArgument')->willReturn(['fileId' => 4]);
		$this->jobs->method('getJobsIterator')->willReturnCallback(static fn (string $class): array => $class === SignFileJob::class ? [$pending] : []);
		$this->statuses->expects($this->never())->method('update');
		$this->cleanup->run(null);
		$this->assertSame(FileStatus::SIGNING_IN_PROGRESS->value, $this->file->getStatus());
	}

	public function testDoesNotResetAnUncertainSignature(): void {
		$request = new SignRequest();
		$request->setId(42);
		$this->requests->method('getByFileId')->with(4)->willReturn([$request]);
		$this->attempts->method('hasUncertainAttempt')->with(42)->willReturn(true);
		$this->statuses->expects($this->never())->method('update');
		$this->cleanup->run(null);
		$this->assertSame(FileStatus::SIGNING_IN_PROGRESS->value, $this->file->getStatus());
	}

	public function testResetsFinishedFailedAttemptAfterTenMinutes(): void {
		$request = new SignRequest();
		$request->setId(42);
		$this->requests->method('getByFileId')->with(4)->willReturn([$request]);
		$this->attempts->method('hasFinishedAttempt')->with(42)->willReturn(true);
		$this->statuses->expects($this->once())->method('update')->with($this->file);
		$this->cleanup->run(null);
		$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $this->file->getStatus());
	}

	public function testDoesNotResetFileWhenSignRequestHasSignedOutput(): void {
		$request = new SignRequest();
		$request->setId(42);
		$request->setSignedHash('signed-request-hash');
		$this->requests->method('getByFileId')->with(4)->willReturn([$request]);
		$this->attempts->method('hasFinishedAttempt')->with(42)->willReturn(true);
		$this->statuses->expects($this->never())->method('update');
		$this->cleanup->run(null);
		$this->assertSame(FileStatus::SIGNING_IN_PROGRESS->value, $this->file->getStatus());
	}

	public function testLegacyFileWithoutCompletionReceiptRequiresReview(): void {
		$this->statuses->expects($this->never())->method('update');
		$this->cleanup->run(null);
		$this->assertSame(FileStatus::SIGNING_IN_PROGRESS->value, $this->file->getStatus());
	}

	public function testOldLegacyFileCannotBeResetJustBecauseItIsOlderThanTwelveHours(): void {
		$this->file->setMetadata(['status_changed_at' => (new DateTime('-14 hours'))->format(DateTime::ATOM)]);
		$this->statuses->expects($this->never())->method('update');
		$this->cleanup->run(null);
		$this->assertSame(FileStatus::SIGNING_IN_PROGRESS->value, $this->file->getStatus());
	}

	public function testChildDoesNotResetWhileParentSigningJobIsPending(): void {
		$this->file->setParentFileId(99);
		$pending = $this->createMock(IJob::class);
		$pending->method('getArgument')->willReturn(['fileId' => 99]);
		$this->jobs->method('getJobsIterator')->willReturnCallback(static fn (string $class): array => $class === SignFileJob::class ? [$pending] : []);
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
		$this->jobs->method('getJobsIterator')->willReturnCallback(static fn (string $class): array => $class === SignSingleFileJob::class ? [$pending] : []);
		$this->statuses->expects($this->never())->method('update');
		$this->statuses->expects($this->never())->method('propagateStatusToParent');
		$this->cleanup->run(null);
		$this->assertSame(FileStatus::SIGNING_IN_PROGRESS->value, $this->file->getStatus());
	}
}
