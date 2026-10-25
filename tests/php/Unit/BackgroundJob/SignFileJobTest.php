<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\BackgroundJob;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\Service\SigningAttemptGuard;
use OCA\Libresign\Service\SignJobCoordinator;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\TestCase;

class SignFileJobTest extends TestCase {
	public function testJobStaysInQueueUntilCoordinatorReturns(): void {
		$jobs = $this->createMock(IJobList::class);
		$guard = $this->createMock(SigningAttemptGuard::class);
		$coordinator = $this->createMock(SignJobCoordinator::class);
		$job = new SignFileJob($this->createMock(ITimeFactory::class), $coordinator, $jobs, $guard);
		$job->setId('job-42');
		$executed = false;
		$guard->method('execute')->willReturnCallback(static function (array $args, string $id, callable $run) use (&$executed): bool {
			self::assertSame('job-42', $id);
			$run();
			$executed = true;
			return true;
		});
		$coordinator->expects($this->once())->method('runSignFile')
			->with(['fileId' => 1])->willReturnCallback(static function () use (&$executed): bool {
				self::assertFalse($executed);
				return true;
			});
		$coordinator->expects($this->never())->method('discardJobCredentials');
		$jobs->expects($this->once())->method('removeById')->with('job-42')
			->willReturnCallback(static function () use (&$executed): void {
				self::assertTrue($executed);
			});
		$job->run(['fileId' => 1]);
	}

	public function testSkippedDuplicateDeletesOnlyItsCredentialsAndJob(): void {
		$jobs = $this->createMock(IJobList::class);
		$guard = $this->createMock(SigningAttemptGuard::class);
		$coordinator = $this->createMock(SignJobCoordinator::class);
		$job = new SignFileJob($this->createMock(ITimeFactory::class), $coordinator, $jobs, $guard);
		$job->setId('job-43');
		$guard->method('execute')->willReturnCallback(static function (array $args, string $jobId, callable $run) use ($job): bool {
			$job->setId('other-job');
			return false;
		});
		$coordinator->expects($this->never())->method('runSignFile');
		$coordinator->expects($this->once())->method('discardJobCredentials')
			->with(['credentialsId' => 'duplicate-credentials']);
		$jobs->expects($this->once())->method('removeById')->with('job-43');
		$job->run(['credentialsId' => 'duplicate-credentials']);
	}

	public function testUnexpectedFailureKeepsJobForRecovery(): void {
		$jobs = $this->createMock(IJobList::class);
		$guard = $this->createMock(SigningAttemptGuard::class);
		$guard->method('execute')->willThrowException(new \RuntimeException('Worker failed'));
		$job = new SignFileJob(
			$this->createMock(ITimeFactory::class),
			$this->createMock(SignJobCoordinator::class),
			$jobs, $guard,
		);
		$jobs->expects($this->never())->method('removeById');
		$this->expectException(\RuntimeException::class);
		$job->run(['signRequestId' => 12]);
	}
}
