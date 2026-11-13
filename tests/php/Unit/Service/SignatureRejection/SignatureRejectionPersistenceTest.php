<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureRejection;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\SignatureRejection\RejectionInput;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionPersistence;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionWorkflow;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SignatureRejectionPersistenceTest extends TestCase {
	use SignatureRejectionSamples;

	private SignRequestMapper&MockObject $signRequests;
	private FileMapper&MockObject $files;
	private SignatureRejectionWorkflow&MockObject $workflow;
	private IDBConnection&MockObject $db;
	private LoggerInterface&MockObject $logger;
	private SignatureRejectionPersistence $persistence;

	protected function setUp(): void {
		$this->signRequests = $this->createMock(SignRequestMapper::class);
		$this->files = $this->createMock(FileMapper::class);
		$this->workflow = $this->createMock(SignatureRejectionWorkflow::class);
		$this->db = $this->createMock(IDBConnection::class);
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getDateTime')->willReturn(new \DateTime(self::REJECTED_AT));
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->persistence = new SignatureRejectionPersistence(
			$this->signRequests, $this->files, $this->workflow,
			$this->db, $clock, $l10n, $this->logger,
		);
	}

	private function input(bool $cancel = false): RejectionInput {
		return new RejectionInput('reason', true, $cancel);
	}

	public function testCommitPersistsRejectionAndReturnsActivatedSigners(): void {
		$file = $this->file();
		$signer = $this->signer();
		$next = $this->signer(id: 77);
		$this->workflow->method('collectSignRequestsToReject')->willReturn([$signer]);
		$this->workflow->method('continueWorkflow')->willReturn([$next]);
		$this->db->expects($this->once())->method('commit');

		$result = $this->persistence->commit($file, $signer, $this->input());

		$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $result->previousRootStatus);
		$this->assertSame([$next], $result->activatedSigners);
		$this->assertSame(SignRequestStatus::REJECTED, $signer->getStatusEnum());
		$this->assertSame(self::REJECTED_AT, $signer->getRejectedAt()?->format(\DateTimeInterface::ATOM));
		$this->assertSame('reason', $signer->getRejectionComment());
		$this->assertTrue($signer->getRejectionCommentPrivate());
	}

	public function testEnvelopePreviousStatusIsCapturedBeforeBusinessCommit(): void {
		$document = $this->file(parentFileId: 1);
		$signer = $this->signer();
		$envelope = $this->envelope(FileStatus::PARTIAL_SIGNED->value);
		$this->files->method('getById')->with(1)->willReturn($envelope);
		$this->workflow->method('collectSignRequestsToReject')->willReturn([$signer]);
		$this->db->method('commit')->willReturnCallback(
			static function () use ($envelope): void {
				$envelope->setStatusEnum(FileStatus::CANCELED);
			},
		);

		$result = $this->persistence->commit($document, $signer, $this->input(true));

		$this->assertSame(FileStatus::PARTIAL_SIGNED->value, $result->previousRootStatus);
		$this->assertSame(FileStatus::CANCELED->value, $envelope->getStatus());
	}

	public function testMissingEnvelopeIsDetectedBeforeAnyWrites(): void {
		$document = $this->file(parentFileId: 1);
		$this->files->method('getById')->with(1)
			->willThrowException(new DoesNotExistException('missing'));
		$this->db->expects($this->never())->method('beginTransaction');
		$this->expectException(DoesNotExistException::class);

		$this->persistence->commit($document, $this->signer(), $this->input());
	}

	public function testCancelPolicyUpdatesWorkflowWithoutActivatingNextSigners(): void {
		$file = $this->file();
		$signer = $this->signer();
		$this->workflow->method('collectSignRequestsToReject')->willReturn([$signer]);
		$this->workflow->expects($this->once())->method('cancelWorkflow')
			->willReturnCallback(static function ($file): void {
				$file->setStatusEnum(FileStatus::CANCELED);
			});
		$this->workflow->expects($this->never())->method('continueWorkflow');

		$result = $this->persistence->commit($file, $signer, $this->input(true));

		$this->assertSame([], $result->activatedSigners);
		$this->assertSame(FileStatus::CANCELED->value, $file->getStatus());
	}

	public function testFailedWriteRollsBackAndRestoresMutatedSigner(): void {
		$file = $this->file();
		$signer = $this->signer();
		$this->workflow->method('collectSignRequestsToReject')->willReturn([$signer]);
		$this->signRequests->method('update')->willThrowException(new \RuntimeException('database error'));
		$this->logger->expects($this->once())->method('error')->with(
			'Failed to register the signature rejection',
			$this->callback(static fn (array $context): bool
				=> ($context['errorType'] ?? null) === \RuntimeException::class
				&& ($context['signRequestId'] ?? null) === $signer->getId()
				&& is_string($context['errorFile'] ?? null)
				&& is_int($context['errorLine'] ?? null)
				&& !array_key_exists('exception', $context)),
		);
		$this->db->expects($this->never())->method('commit');
		$this->db->expects($this->once())->method('rollBack');
		$this->expectException(LibresignException::class);

		try {
			$this->persistence->commit($file, $signer, $this->input());
		} finally {
			$this->assertSame(SignRequestStatus::ABLE_TO_SIGN, $signer->getStatusEnum());
			$this->assertNull($signer->getRejectedAt());
			$this->assertNull($signer->getRejectionComment());
			$this->assertFalse($signer->getRejectionCommentPrivate());
		}
	}

	public function testFailedWorkflowUpdateRollsBackPersistedRejectionAndFileStatus(): void {
		$file = $this->file();
		$signer = $this->signer();
		$this->workflow->method('collectSignRequestsToReject')->willReturn([$signer]);
		$this->workflow->method('continueWorkflow')->willReturnCallback(
			static function ($file): array {
				$file->setStatusEnum(FileStatus::SIGNED);
				throw new \RuntimeException('signing status update failed');
			},
		);
		$this->db->expects($this->once())->method('rollBack');
		$this->db->expects($this->never())->method('commit');
		$this->expectException(LibresignException::class);

		try {
			$this->persistence->commit($file, $signer, $this->input());
		} finally {
			$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $file->getStatus());
			$this->assertSame(SignRequestStatus::ABLE_TO_SIGN, $signer->getStatusEnum());
		}
	}

	public function testProgrammingErrorRollsBackWithoutBeingConvertedIntoPolicyFailure(): void {
		$signer = $this->signer();
		$this->workflow->method('collectSignRequestsToReject')->willReturn([$signer]);
		$this->signRequests->method('update')->willThrowException(new \TypeError('bad state'));
		$this->db->expects($this->once())->method('rollBack');
		$this->db->expects($this->never())->method('commit');
		$this->expectException(\TypeError::class);

		$this->persistence->commit($this->file(), $signer, $this->input());
	}

	public function testSecondSignerWriteFailureRestoresAllRejections(): void {
		$first = $this->signer(id: 11);
		$second = $this->signer(id: 12);
		$this->workflow->method('collectSignRequestsToReject')->willReturn([$first, $second]);
		$writes = [];
		$this->signRequests->method('update')->willReturnCallback(
			static function (SignRequest $signer) use (&$writes): SignRequest {
				$writes[] = $signer->getId();
				if (count($writes) === 2) {
					throw new \RuntimeException('second write failed');
				}
				return $signer;
			},
		);
		$this->db->expects($this->once())->method('rollBack');
		$this->db->expects($this->never())->method('commit');
		$this->expectException(LibresignException::class);

		try {
			$this->persistence->commit($this->file(), $first, $this->input());
		} finally {
			$this->assertSame([11, 12], $writes);
			foreach ([$first, $second] as $signer) {
				$this->assertSame(SignRequestStatus::ABLE_TO_SIGN, $signer->getStatusEnum());
				$this->assertNull($signer->getRejectedAt());
				$this->assertNull($signer->getRejectionComment());
			}
		}
	}

	public function testFailedCancellationRestoresFileAndSignerBeforeReturningError(): void {
		$file = $this->file(FileStatus::PARTIAL_SIGNED->value);
		$signer = $this->signer();
		$this->workflow->method('collectSignRequestsToReject')->willReturn([$signer]);
		$this->workflow->method('cancelWorkflow')->willReturnCallback(
			static function ($file): void {
				$file->setStatusEnum(FileStatus::CANCELED);
				throw new \RuntimeException('failed to update envelope');
			},
		);
		$this->db->expects($this->once())->method('rollBack');
		$this->db->expects($this->never())->method('commit');
		$this->expectException(LibresignException::class);

		try {
			$this->persistence->commit($file, $signer, $this->input(true));
		} finally {
			$this->assertSame(FileStatus::PARTIAL_SIGNED->value, $file->getStatus());
			$this->assertSame(SignRequestStatus::ABLE_TO_SIGN, $signer->getStatusEnum());
		}
	}

	public function testWorkflowChangesOccurBeforeTransactionCommit(): void {
		$file = $this->file();
		$signer = $this->signer();
		$this->workflow->method('collectSignRequestsToReject')->willReturn([$signer]);
		$order = [];
		$this->workflow->method('continueWorkflow')->willReturnCallback(
			static function () use (&$order): array {
				$order[] = 'status';
				return [];
			},
		);
		$this->db->expects($this->once())->method('commit')->willReturnCallback(
			static function () use (&$order): void {
				$order[] = 'commit';
			},
		);

		$this->persistence->commit($file, $signer, $this->input());
		$this->assertSame(['status', 'commit'], $order);
	}
}
