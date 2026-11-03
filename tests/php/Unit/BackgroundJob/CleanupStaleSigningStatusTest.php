<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\BackgroundJob;

use OCA\Libresign\BackgroundJob\CleanupStaleSigningStatus;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CleanupStaleSigningStatusTest extends TestCase {
	/** @return array<string, array{int}> */
	public static function staleFileCases(): array {
		return [
			'no stale signing' => [0],
			'one stale signature' => [1],
			'many stale signatures' => [15],
		];
	}

	#[DataProvider('staleFileCases')]
	public function testReportsStaleSigningWithoutReleasingAnyFile(int $numberOfFiles): void {
		$files = [];
		for ($id = 1; $id <= $numberOfFiles; $id++) {
			$file = new File();
			$file->setId($id);
			$file->setStatusEnum(FileStatus::SIGNING_IN_PROGRESS);
			$files[] = $file;
		}
		$mapper = $this->createMock(FileMapper::class);
		$mapper->method('findStaleSigningInProgress')->willReturn($files);
		$mapper->expects($this->never())->method('update');
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($numberOfFiles === 0 ? $this->never() : $this->once())
			->method('warning')
			->with($this->anything(), $this->callback(static fn (array $context): bool
				=> $context['count'] === $numberOfFiles
				&& count($context['fileIds']) === min($numberOfFiles, 10)));

		$job = new CleanupStaleSigningStatus($this->createMock(ITimeFactory::class), $mapper, $logger);
		$job->run(null);
		foreach ($files as $file) {
			$this->assertSame(FileStatus::SIGNING_IN_PROGRESS->value, $file->getStatus());
		}
	}

	public function testReadFailureIsReportedToNextcloudRatherThanHidden(): void {
		$mapper = $this->createMock(FileMapper::class);
		$mapper->method('findStaleSigningInProgress')->willThrowException(new \RuntimeException('Database unavailable'));
		$job = new CleanupStaleSigningStatus(
			$this->createMock(ITimeFactory::class), $mapper, $this->createMock(LoggerInterface::class),
		);
		$this->expectException(\RuntimeException::class);
		$job->run(null);
	}
}
