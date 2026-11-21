<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\IdDoc;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocs;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdDoc\IdDocRejectionNotifier;
use OCA\Libresign\Service\IdDoc\IdDocRejectionService;
use OCA\Libresign\Service\IdDoc\IdDocsPolicyService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;

final class IdDocRejectionServiceTest extends TestCase {
	private IdDocsPolicyService $policy;
	private IdDocsMapper $documents;
	private FileMapper $files;
	private IdDocRejectionNotifier $notifier;
	private IDBConnection $db;
	private bool $documentApproverAllowed = true;
	private IdDocRejectionService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->policy = $this->createMock(IdDocsPolicyService::class);
		$this->documents = $this->createMock(IdDocsMapper::class);
		$this->files = $this->createMock(FileMapper::class);
		$this->notifier = $this->createMock(IdDocRejectionNotifier::class);
		$this->db = $this->createMock(IDBConnection::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getDateTime')->willReturn(new \DateTime('2026-11-10T12:00:00Z'));
		$this->policy->method('canApproverSignIdDoc')
			->willReturnCallback(fn (): bool => $this->documentApproverAllowed);
		$this->service = new IdDocRejectionService(
			$this->policy, $this->documents, $this->files, $clock, $l10n, $this->db,
			$this->notifier,
		);
	}

	#[DataProvider('unauthorizedReviewers')]
	public function testUnauthorizedReviewerCannotReadOrRejectDocument(bool $hasUser): void {
		$this->policy->method('userCanApproveValidationDocuments')->willReturn(false);
		$this->documents->expects($this->never())->method('getByNodeId');
		$this->notifier->expects($this->never())->method('queue');
		$this->notifier->expects($this->never())->method('flushCommitted');
		$this->expectException(LibresignException::class);
		$this->service->reject(11, 'Expired document', $hasUser ? $this->createMock(IUser::class) : null);
	}

	public static function unauthorizedReviewers(): array {
		return [
			'unauthenticated' => [false],
			'account without reviewer permission' => [true],
		];
	}

	#[DataProvider('invalidReasons')]
	public function testInvalidReasonCannotReadOrRejectDocument(string $reason): void {
		$this->policy->method('userCanApproveValidationDocuments')->willReturn(true);
		$this->documents->expects($this->never())->method('getByNodeId');
		$this->notifier->expects($this->never())->method('queue');
		$this->expectException(LibresignException::class);
		$this->service->reject(11, $reason, $this->createMock(IUser::class));
	}

	public static function invalidReasons(): array {
		return [
			'empty' => [''],
			'whitespace only' => ["\t\n "],
			'long ASCII' => [str_repeat('a', 1025)],
			'long Unicode' => [str_repeat('é', 1025)],
		];
	}

	#[DataProvider('unapprovableStates')]
	public function testCannotRejectDocumentNotWaitingForReview(int $status, bool $alreadyRejected): void {
		$this->policy->method('userCanApproveValidationDocuments')->willReturn(true);
		$document = $this->document($alreadyRejected);
		$file = $this->file($status);
		$this->documents->method('getByNodeId')->with(11)->willReturn($document);
		$this->files->method('getById')->with(9)->willReturn($file);
		$this->documents->expects($this->never())->method('rejectIfPending');
		$this->notifier->expects($this->never())->method('queue');
		$this->expectException(LibresignException::class);
		$this->service->reject(11, 'Expired document', $this->createMock(IUser::class));
	}

	public static function unapprovableStates(): array {
		return [
			'draft' => [FileStatus::DRAFT->value, false],
			'signed' => [FileStatus::SIGNED->value, false],
			'canceled' => [FileStatus::CANCELED->value, false],
			'already rejected' => [FileStatus::ABLE_TO_SIGN->value, true],
		];
	}

	public function testDocumentSpecificAuthorizationPreventsRejection(): void {
		$this->policy->method('userCanApproveValidationDocuments')->willReturn(true);
		$this->documentApproverAllowed = false;
		$this->documents->method('getByNodeId')->willReturn($this->document());
		$this->files->method('getById')->willReturn($this->file());
		$this->documents->expects($this->never())->method('rejectIfPending');
		$this->expectException(LibresignException::class);
		$this->service->reject(11, 'Expired document', $this->createMock(IUser::class));
	}

	public function testConcurrentRejectionDoesNotOverwriteAuditOrNotify(): void {
		$this->policy->method('userCanApproveValidationDocuments')->willReturn(true);
		$this->documents->method('getByNodeId')->willReturn($this->document());
		$this->files->method('getById')->willReturn($this->file());
		$this->documents->method('rejectIfPending')->willReturn(false);
		$this->notifier->expects($this->never())->method('queue');
		$this->notifier->expects($this->never())->method('flushCommitted');
		$this->expectException(LibresignException::class);
		$this->service->reject(11, 'Expired document', $this->createMock(IUser::class));
	}

	#[DataProvider('approvableStates')]
	public function testSuccessfulRejectionRecordsAuditAndQueuesNotification(int $status, string $reason, string $expectedReason): void {
		$this->policy->method('userCanApproveValidationDocuments')->willReturn(true);
		$approver = $this->createMock(IUser::class);
		$approver->method('getUID')->willReturn('reviewer');
		$document = $this->document();
		$file = $this->file($status);
		$this->documents->method('getByNodeId')->with(11)->willReturn($document);
		$this->files->method('getById')->with(9)->willReturn($file);
		$this->documents->expects($this->once())->method('rejectIfPending')
			->with($document, 'reviewer', $expectedReason, $this->isInstanceOf(\DateTime::class))
			->willReturn(true);
		$this->notifier->expects($this->once())->method('queue')->with($document, $file, $expectedReason);
		$this->notifier->expects($this->once())->method('flushCommitted');
		$this->service->reject(11, $reason, $approver);
	}

	public static function approvableStates(): array {
		return [
			'ready to sign' => [FileStatus::ABLE_TO_SIGN->value, ' Expired document ', 'Expired document'],
			'partially signed' => [FileStatus::PARTIAL_SIGNED->value, 'Photo is blurry', 'Photo is blurry'],
			'Unicode boundary accepted' => [FileStatus::ABLE_TO_SIGN->value, str_repeat('é', 1024), str_repeat('é', 1024)],
		];
	}

	public function testRejectionNotificationIsQueuedBeforeCommitAndFlushedAfter(): void {
		$this->policy->method('userCanApproveValidationDocuments')->willReturn(true);
		$approver = $this->createMock(IUser::class);
		$approver->method('getUID')->willReturn('reviewer');
		$doc = $this->document();
		$file = $this->file();
		$this->documents->method('getByNodeId')->willReturn($doc);
		$this->files->method('getById')->willReturn($file);
		$observed = [];
		$this->db->method('beginTransaction')->willReturnCallback(static function () use (&$observed): void {
			$observed[] = 'begin';
		});
		$this->documents->method('rejectIfPending')->willReturnCallback(
			static function () use (&$observed): bool {
				$observed[] = 'audit persisted';
				return true;
			},
		);
		$this->notifier->method('queue')->willReturnCallback(static function () use (&$observed): void {
			$observed[] = 'notification queued';
		});
		$this->db->method('commit')->willReturnCallback(static function () use (&$observed): void {
			$observed[] = 'commit';
		});
		$this->notifier->method('flushCommitted')->willReturnCallback(
			static function () use (&$observed): void {
				$observed[] = 'delivery flushed';
			},
		);

		$this->service->reject(11, 'Expired document', $approver);

		$this->assertSame(['begin', 'audit persisted', 'notification queued', 'commit', 'delivery flushed'], $observed);
	}

	public function testQueueFailureRollsBackRejectionWithoutDelivery(): void {
		$this->policy->method('userCanApproveValidationDocuments')->willReturn(true);
		$approver = $this->createMock(IUser::class);
		$approver->method('getUID')->willReturn('reviewer');
		$doc = $this->document();
		$this->documents->method('getByNodeId')->willReturn($doc);
		$this->files->method('getById')->willReturn($this->file());
		$this->documents->method('rejectIfPending')->willReturn(true);
		$this->notifier->method('queue')->willThrowException(new \RuntimeException('Outbox unavailable'));
		$this->db->expects($this->once())->method('rollBack');
		$this->db->expects($this->never())->method('commit');
		$this->notifier->expects($this->never())->method('flushCommitted');
		$this->expectException(\RuntimeException::class);
		$this->service->reject(11, 'Expired document', $approver);
	}

	private function document(bool $rejected = false): IdDocs {
		$document = new IdDocs();
		$document->setId(7);
		$document->setFileId(9);
		$document->setSignRequestId(42);
		if ($rejected) {
			$document->setRejectedAt(new \DateTime('2026-11-09T00:00:00Z'));
		}
		return $document;
	}

	private function file(int $status = FileStatus::ABLE_TO_SIGN->value): File {
		$file = new File();
		$file->setId(9);
		$file->setStatus($status);
		return $file;
	}
}
