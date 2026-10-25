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
use PHPUnit\Framework\Attributes\DataProvider;
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
		$this->assertTrue($this->guard->execute(['signRequestId' => 42], 'job-123', function () use (&$phases): void {
			$phases[] = 'sign';
		}));
		$this->assertSame(['running', 'sign', 'finished'], $phases);
		$this->assertFalse($this->guard->execute(['signRequestId' => 42], 'job-123', function (): void {
			$this->fail('Finished job must not sign twice');
		}));
	}

	/** @return array<string, array{?string, array<string, mixed>}> */
	public static function completedRequestCases(): array {
		return [
			'already signed PDF' => ['existing-hash', []],
			'durable completion receipt' => [null, ['async_signing_attempt' => [
				'jobId' => 'original-job', 'phase' => 'finished', 'startedAt' => 1,
			]]],
		];
	}

	#[DataProvider('completedRequestCases')]
	public function testCompletedSignatureIsNeverRepeated(?string $signedHash, array $metadata): void {
		$this->request->setMetadata($metadata);
		if ($signedHash !== null) {
			$this->request->setSignedHash($signedHash);
		}
		$this->assertFalse($this->guard->execute(['signRequestId' => 42], 'replacement-job', function (): void {
			$this->fail('A completed signing attempt must never run twice');
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
		$this->assertSame('uncertain', $this->request->getMetadata()['async_signing_attempt']['phase']);
		$this->assertSame('uncertain', $this->request->getMetadata()['async_signing_attempt']['phase']);
	}

	public function testSecondJobDoesNotMarkActiveFirstJobAsInterrupted(): void {
		$this->request->setMetadata(['async_signing_attempt' => [
			'jobId' => '12345', 'phase' => 'running', 'startedAt' => time(),
		]]);
		$this->jobs->expects($this->once())->method('getDetailsById')->with('12345')->willReturn(['id' => '12345']);
		$this->reporter->expects($this->never())->method('error');
		$this->mapper->expects($this->never())->method('update');
		$this->assertFalse($this->guard->execute(['signRequestId' => 42], '12346', function (): void {
			$this->fail('Duplicate job must not sign');
		}));
		$this->assertSame('running', $this->request->getMetadata()['async_signing_attempt']['phase']);
	}

	public function testInvalidPersistedJobIdNeedsReviewWithoutQueryingTheQueue(): void {
		$this->request->setMetadata(['async_signing_attempt' => [
			'jobId' => 'invalid-job-id', 'phase' => 'running', 'startedAt' => 1,
		]]);
		$this->jobs->expects($this->never())->method('getDetailsById');
		$this->reporter->expects($this->once())->method('error');
		$this->assertFalse($this->guard->execute(['signRequestId' => 42], '12346', function (): void {
			$this->fail('A malformed previous job ID cannot authorize another signature');
		}));
		$this->assertSame('uncertain', $this->request->getMetadata()['async_signing_attempt']['phase']);
	}

	public function testMalformedJobReturnsHandledFailureWithoutCreatingReceipt(): void {
		$this->locking->expects($this->never())->method('acquireLock');
		$this->mapper->expects($this->never())->method('getByIdUncached');
		$this->assertFalse($this->guard->execute(['fileId' => -1], 'bad-job', static fn (): bool => false));
	}

	public function testHandledSigningFailureIsNotMarkedFinished(): void {
		$updates = [];
		$this->mapper->method('update')->willReturnCallback(static function (SignRequest $request) use (&$updates): SignRequest {
			$updates[] = $request->getMetadata()['async_signing_attempt']['phase'];
			return $request;
		});
		$this->assertFalse($this->guard->execute(['signRequestId' => 42], 'job-123', static fn (): bool => false));
		$this->assertSame(['running', 'uncertain'], $updates);
		$this->assertSame('uncertain', $this->request->getMetadata()['async_signing_attempt']['phase']);
		$this->assertNotSame('finished', $this->request->getMetadata()['async_signing_attempt']['phase']);
	}

	public function testUnhandledFailureRetainsRunningReceipt(): void {
		$this->expectException(\RuntimeException::class);
		try {
			$this->guard->execute(['signRequestId' => 42], 'job-123', static function (): void {
				throw new \RuntimeException('Signing stopped unexpectedly');
			});
		} finally {
			$this->assertSame('running', $this->request->getMetadata()['async_signing_attempt']['phase']);
		}
	}
}
