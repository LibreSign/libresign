<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\SigningProgressService;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class SigningProgressServiceTest extends TestCase {
	private FileMapper&MockObject $fileMapper;
	private LoggerInterface&MockObject $logger;
	private SigningProgressService $service;

	public function setUp(): void {
		parent::setUp();
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->service = new SigningProgressService($this->fileMapper, $this->logger);
	}

	public function testSetInProgressUpdatesOnlyWhenNotAlready(): void {
		$file1 = new File();
		$file1->setStatusEnum(FileStatus::ABLE_TO_SIGN);

		$file2 = new File();
		$file2->setStatusEnum(FileStatus::SIGNING_IN_PROGRESS);

		$this->fileMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (File $f) use ($file1) {
				return $f === $file1 && $f->getStatus() === FileStatus::SIGNING_IN_PROGRESS->value;
			}));

		$this->service->setInProgressStatus([
			['file' => $file1, 'signRequest' => null],
			['file' => $file2, 'signRequest' => null],
		]);

		$this->assertSame(FileStatus::SIGNING_IN_PROGRESS->value, $file1->getStatus());
		$this->assertSame(FileStatus::SIGNING_IN_PROGRESS->value, $file2->getStatus());
	}

	public function testRevertInProgressRevertsOnlyThoseInProgressAndLogsOnError(): void {
		$file1 = new File();
		$file1->setStatusEnum(FileStatus::SIGNING_IN_PROGRESS);

		$file2 = new File();
		$file2->setStatusEnum(FileStatus::ABLE_TO_SIGN);

		$file3 = new File();
		$file3->setStatusEnum(FileStatus::DRAFT);

		$this->fileMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (File $f) use ($file1) {
				return $f === $file1 && $f->getStatus() === FileStatus::ABLE_TO_SIGN->value;
			}));

		$this->logger->expects($this->once())
			->method('error');

		$ex = new \RuntimeException('boom');
		$this->service->revertInProgressStatus([$file1, $file2, $file3], $ex);

		$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $file1->getStatus());
		$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $file2->getStatus());
		$this->assertSame(FileStatus::DRAFT->value, $file3->getStatus());
	}
}
