<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\SigningAttemptGuard;
use OCA\Libresign\Service\SignRequest\Error\SignRequestErrorReporter;
use OCP\BackgroundJob\IJobList;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SigningAttemptGuardTest extends TestCase {
	private SignRequestMapper&MockObject $mapper;
	private ILockingProvider&MockObject $locking;
	private IJobList&MockObject $jobs;
	private SignRequestErrorReporter&MockObject $reporter;
	private SigningAttemptGuard $guard;
	private SignRequest $request;

	protected function setUp(): void {
		$this->mapper = $this->createMock(SignRequestMapper::class);
		$this->locking = $this->createMock(ILockingProvider::class);
		$this->jobs = $this->createMock(IJobList::class);
		$this->reporter = $this->createMock(SignRequestErrorReporter::class);
		$this->request = new SignRequest();
		$this->request->setId(42);
		$this->request->setUuid('request-uuid');
		$this->request->setMetadata([]);
		$this->mapper->method('getByIdUncached')->with(42)->willReturn($this->request);
		$this->guard = new SigningAttemptGuard(
			$this->mapper, $this->locking, $this->jobs, $this->reporter, $this->createMock(LoggerInterface::class),
		);
	}

	public function testPersistsAttemptBeforeSigningAndMarksCompleteAfterReturn(): void {
		$phases = [];
		$this->mapper->expects($this->exactly(2))->method('update')->willReturnCallback(function (SignRequest $request) use (&$phases): SignRequest {
			$phases[] = $request->getMetadata()['async_signing_attempt']['phase'];
			return $request;
		});
		$this->locking->expects($this->exactly(3))->method('acquireLock');
		$this->locking->expects($this->exactly(3))->method('releaseLock');
		$this->assertTrue($this->guard->execute(['signRequestId' => 42], 'job-123', function () use (&$phases): void {
			$phases[] = 'sign';
		}));
		$this->assertSame(['running', 'sign', 'finished'], $phases);
		$this->assertFalse($this->guard->execute(['signRequestId' => 42], 'job-123', function (): void {
			$this->fail('Finished job must not sign twice');
		}));
	}

	public function testRecoveryOfUncertainAttemptReportsErrorInsteadOfSigningAgain(): void {
		$this->request->setMetadata(['async_signing_attempt' => [
			'jobId' => 'old-job', 'phase' => 'running', 'startedAt' => 1,
		]]);
		$this->reporter->expects($this->once())->method('error')->with(
			$this->anything(),
			$this->callback(static fn (array $context): bool => $context['exception'] instanceof \RuntimeException),
		);
		$this->assertFalse($this->guard->execute(['signRequestId' => 42, 'fileId' => 7], 'new-job', function (): void {
			$this->fail('Ambiguous signing must not repeat');
		}));
		$this->assertTrue($this->guard->hasUncertainAttempt(42));
		$this->assertSame('uncertain', $this->request->getMetadata()['async_signing_attempt']['phase']);
	}

	public function testSecondJobDoesNotMarkActiveFirstJobAsInterrupted(): void {
		$this->request->setMetadata(['async_signing_attempt' => [
			'jobId' => 'active-job', 'phase' => 'running', 'startedAt' => time(),
		]]);
		$this->jobs->expects($this->once())->method('getDetailsById')->with('active-job')->willReturn(['id' => 'active-job']);
		$this->reporter->expects($this->never())->method('error');
		$this->mapper->expects($this->never())->method('update');
		$this->assertFalse($this->guard->execute(['signRequestId' => 42], 'duplicate-job', function (): void {
			$this->fail('Duplicate job must not sign');
		}));
		$this->assertSame('running', $this->request->getMetadata()['async_signing_attempt']['phase']);
	}

	public function testMalformedJobReturnsHandledFailureWithoutCreatingReceipt(): void {
		$this->locking->expects($this->never())->method('acquireLock');
		$this->mapper->expects($this->never())->method('getByIdUncached');
		$this->assertFalse($this->guard->execute(['fileId' => -1], 'bad-job', static fn (): bool => false));
	}

	public function testSignedRequestSkipsExecution(): void {
		$this->request->setSignedHash('sha256');
		$this->mapper->expects($this->never())->method('update');
		$this->assertFalse($this->guard->execute(['signRequestId' => 42], 'job-123', function (): void {
			$this->fail('Completed signature must not repeat');
		}));
	}

	public function testHandledSigningFailureIsNotMarkedFinished(): void {
		$updates = [];
		$this->mapper->method('update')->willReturnCallback(static function (SignRequest $request) use (&$updates): SignRequest {
			$updates[] = $request->getMetadata()['async_signing_attempt']['phase'];
			return $request;
		});
		$this->assertFalse($this->guard->execute(['signRequestId' => 42], 'job-123', static fn (): bool => false));
		$this->assertSame(['running', 'uncertain'], $updates);
		$this->assertTrue($this->guard->hasUncertainAttempt(42));
		$this->assertFalse($this->guard->hasFinishedAttempt(42));
	}

	public function testUnhandledFailureRetainsRunningReceipt(): void {
		$this->expectException(\RuntimeException::class);
		try {
			$this->guard->execute(['signRequestId' => 42], 'job-123', static function (): void {
				throw new \RuntimeException('Signing stopped unexpectedly');
			});
		} finally {
			$this->assertTrue($this->guard->hasUncertainAttempt(42));
			$this->assertSame('running', $this->request->getMetadata()['async_signing_attempt']['phase']);
		}
	}
}
