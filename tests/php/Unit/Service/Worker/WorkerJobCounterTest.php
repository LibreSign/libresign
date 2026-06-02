<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\NodeType;
use OCA\Libresign\Service\Worker\WorkerJobCounter;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class WorkerJobCounterTest extends TestCase {
	private IJobList&MockObject $jobList;
	private FileMapper&MockObject $fileMapper;
	private LoggerInterface&MockObject $logger;

	public function setUp(): void {
		parent::setUp();
		$this->jobList = $this->createMock(IJobList::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function makeCounter(): WorkerJobCounter {
		return new WorkerJobCounter($this->jobList, $this->fileMapper, $this->logger);
	}

	private function makeSignFileJob(array $argument): SignFileJob&MockObject {
		$job = $this->createMock(SignFileJob::class);
		$job->method('getArgument')
			->willReturn($argument);
		return $job;
	}

	private function makeSignSingleFileJob(array $argument): SignSingleFileJob&MockObject {
		$job = $this->createMock(SignSingleFileJob::class);
		$job->method('getArgument')
			->willReturn($argument);
		return $job;
	}

	public function testCountPendingJobsReturnsZeroWhenNoJobs(): void {
		$signFileJobs = [];
		$signSingleJobs = [];
		$callIndex = 0;
		$this->jobList->expects($this->exactly(2))
			->method('getJobsIterator')
			->willReturnCallback(function (string $class) use (&$callIndex, $signFileJobs, $signSingleJobs) {
				$callIndex++;
				if ($callIndex === 1) {
					$this->assertSame(SignFileJob::class, $class);
					return $signFileJobs;
				}
				$this->assertSame(SignSingleFileJob::class, $class);
				return $signSingleJobs;
			});

		$counter = $this->makeCounter();
		$this->assertSame(0, $counter->countPendingJobs());
	}

	public function testCountPendingJobsCountsSignFileJobsBasedOnEnvelopeFiles(): void {
		$signFileJob = $this->makeSignFileJob(['fileId' => 42]);
		$envelope = new FileEntity();
		$envelope->setId(42);
		$envelope->setNodeTypeEnum(NodeType::ENVELOPE);

		$signFileJobs = [$signFileJob];
		$signSingleJobs = [];
		$callIndex = 0;
		$this->jobList->expects($this->exactly(2))
			->method('getJobsIterator')
			->willReturnCallback(function (string $class) use (&$callIndex, $signFileJobs, $signSingleJobs) {
				$callIndex++;
				if ($callIndex === 1) {
					$this->assertSame(SignFileJob::class, $class);
					return $signFileJobs;
				}
				$this->assertSame(SignSingleFileJob::class, $class);
				return $signSingleJobs;
			});

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with(42)
			->willReturn($envelope);

		$this->fileMapper->expects($this->once())
			->method('countChildrenFiles')
			->with(42)
			->willReturn(3);

		$counter = $this->makeCounter();
		$this->assertSame(3, $counter->countPendingJobs());
	}

	public function testCountPendingJobsCountsSignFileJobsAsOneWhenNotEnvelope(): void {
		$signFileJob = $this->makeSignFileJob(['fileId' => 7]);
		$file = new FileEntity();
		$file->setId(7);
		$file->setNodeTypeEnum(NodeType::FILE);

		$signFileJobs = [$signFileJob];
		$signSingleJobs = [];
		$callIndex = 0;
		$this->jobList->expects($this->exactly(2))
			->method('getJobsIterator')
			->willReturnCallback(function (string $class) use (&$callIndex, $signFileJobs, $signSingleJobs) {
				$callIndex++;
				if ($callIndex === 1) {
					$this->assertSame(SignFileJob::class, $class);
					return $signFileJobs;
				}
				$this->assertSame(SignSingleFileJob::class, $class);
				return $signSingleJobs;
			});

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with(7)
			->willReturn($file);

		$counter = $this->makeCounter();
		$this->assertSame(1, $counter->countPendingJobs());
	}

	public function testCountPendingJobsCountsSignSingleFileJobs(): void {
		$signSingleJob = $this->makeSignSingleFileJob(['fileId' => 1]);
		$signSingleJobTwo = $this->makeSignSingleFileJob(['fileId' => 2]);

		$signFileJobs = [];
		$signSingleJobs = [$signSingleJob, $signSingleJobTwo];
		$callIndex = 0;
		$this->jobList->expects($this->exactly(2))
			->method('getJobsIterator')
			->willReturnCallback(function (string $class) use (&$callIndex, $signFileJobs, $signSingleJobs) {
				$callIndex++;
				if ($callIndex === 1) {
					$this->assertSame(SignFileJob::class, $class);
					return $signFileJobs;
				}
				$this->assertSame(SignSingleFileJob::class, $class);
				return $signSingleJobs;
			});

		$counter = $this->makeCounter();
		$this->assertSame(2, $counter->countPendingJobs());
	}

	public function testCountPendingJobsSumsBothJobTypes(): void {
		$signFileJob = $this->makeSignFileJob(['fileId' => 55]);
		$envelope = new FileEntity();
		$envelope->setId(55);
		$envelope->setNodeTypeEnum(NodeType::ENVELOPE);
		$signSingleJob = $this->makeSignSingleFileJob(['fileId' => 99]);

		$signFileJobs = [$signFileJob];
		$signSingleJobs = [$signSingleJob];
		$callIndex = 0;
		$this->jobList->expects($this->exactly(2))
			->method('getJobsIterator')
			->willReturnCallback(function (string $class) use (&$callIndex, $signFileJobs, $signSingleJobs) {
				$callIndex++;
				if ($callIndex === 1) {
					$this->assertSame(SignFileJob::class, $class);
					return $signFileJobs;
				}
				$this->assertSame(SignSingleFileJob::class, $class);
				return $signSingleJobs;
			});

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with(55)
			->willReturn($envelope);

		$this->fileMapper->expects($this->once())
			->method('countChildrenFiles')
			->with(55)
			->willReturn(4);

		$counter = $this->makeCounter();
		$this->assertSame(5, $counter->countPendingJobs());
	}

	public function testCountPendingJobsReturnsZeroOnException(): void {
		$this->jobList->expects($this->once())
			->method('getJobsIterator')
			->with(SignFileJob::class, null, 0)
			->will($this->throwException(new \RuntimeException('Database error')));

		$this->logger->expects($this->once())
			->method('debug')
			->with(
				$this->stringContains('Failed to count pending jobs'),
				$this->arrayHasKey('error')
			);

		$counter = $this->makeCounter();
		$this->assertSame(0, $counter->countPendingJobs());
	}

	public function testCountPendingJobsHandlesLargeNumbers(): void {
		$signFileJob = $this->makeSignFileJob(['fileId' => 101]);
		$envelope = new FileEntity();
		$envelope->setId(101);
		$envelope->setNodeTypeEnum(NodeType::ENVELOPE);

		$signSingleJobOne = $this->makeSignSingleFileJob(['fileId' => 1]);
		$signSingleJobTwo = $this->makeSignSingleFileJob(['fileId' => 2]);
		$signSingleJobThree = $this->makeSignSingleFileJob(['fileId' => 3]);

		$signFileJobs = [$signFileJob];
		$signSingleJobs = [$signSingleJobOne, $signSingleJobTwo, $signSingleJobThree];
		$callIndex = 0;
		$this->jobList->expects($this->exactly(2))
			->method('getJobsIterator')
			->willReturnCallback(function (string $class) use (&$callIndex, $signFileJobs, $signSingleJobs) {
				$callIndex++;
				if ($callIndex === 1) {
					$this->assertSame(SignFileJob::class, $class);
					return $signFileJobs;
				}
				$this->assertSame(SignSingleFileJob::class, $class);
				return $signSingleJobs;
			});

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with(101)
			->willReturn($envelope);

		$this->fileMapper->expects($this->once())
			->method('countChildrenFiles')
			->with(101)
			->willReturn(1000);

		$counter = $this->makeCounter();
		$this->assertSame(1003, $counter->countPendingJobs());
	}
}
