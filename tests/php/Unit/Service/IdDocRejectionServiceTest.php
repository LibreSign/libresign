<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocs;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdDocRejectionService;
use OCA\Libresign\Service\IdDocsPolicyService;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\Notification\InAppChannel;
use OCA\Libresign\Service\Notification\MailChannel;
use OCA\Libresign\Service\NotificationDeliveryService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager;
use PHPUnit\Framework\Attributes\DataProvider;

final class IdDocRejectionServiceTest extends TestCase {
	private IdDocsPolicyService $policy;
	private IdDocsMapper $documents;
	private FileMapper $files;
	private NotificationDeliveryService $notifications;
	private IDBConnection $db;
	private IL10N $l10n;
	private IdDocRejectionService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->policy = $this->createMock(IdDocsPolicyService::class);
		$this->documents = $this->createMock(IdDocsMapper::class);
		$this->files = $this->createMock(FileMapper::class);
		$this->notifications = $this->createMock(NotificationDeliveryService::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->policy->method('canApproverSignIdDoc')->willReturn(true);
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getDateTime')->willReturn(new \DateTime('2026-11-10T12:00:00Z'));
		$this->service = new IdDocRejectionService(
			$this->policy,
			$this->documents,
			$this->files,
			$this->createMock(SignRequestMapper::class),
			$this->createMock(IdentifyMethodMapper::class),
			$this->createMock(IUserManager::class),
			$clock,
			$this->l10n,
			$this->db,
			$this->notifications,
			new MailChannel($this->createMock(MailService::class)),
			new InAppChannel($this->createMock(IManager::class)),
			$this->createMock(IManager::class),
		);
	}

	public function testUnauthenticatedAndUnauthorizedUsersCannotReject(): void {
		$this->policy->method('userCanApproveValidationDocuments')->willReturn(false);
		$this->documents->expects($this->never())->method('getByNodeId');
		$this->expectException(LibresignException::class);
		$this->service->reject(11, 'Expired document', null);
	}

	#[DataProvider('invalidReasons')]
	public function testReasonMustBeProvidedAndLimited(string $reason): void {
		$this->policy->method('userCanApproveValidationDocuments')->willReturn(true);
		$this->documents->expects($this->never())->method('getByNodeId');
		$this->expectException(LibresignException::class);
		$this->service->reject(11, $reason, $this->createMock(IUser::class));
	}

	public static function invalidReasons(): array {
		return [[' '], [str_repeat('a', 1025)]];
	}

	public function testApprovedDocumentCannotBeRejected(): void {
		$this->policy->method('userCanApproveValidationDocuments')->willReturn(true);
		$doc = new IdDocs();
		$doc->setId(7);
		$doc->setFileId(9);
		$file = new File();
		$file->setId(9);
		$file->setStatus(FileStatus::SIGNED->value);
		$this->documents->method('getByNodeId')->with(11)->willReturn($doc);
		$this->files->method('getById')->with(9)->willReturn($file);
		$this->documents->expects($this->never())->method('rejectIfPending');
		$this->expectException(LibresignException::class);
		$this->service->reject(11, 'Expired document', $this->createMock(IUser::class));
	}

	public function testRejectingAlreadyRejectedDocumentDoesNotOverwriteAudit(): void {
		$this->policy->method('userCanApproveValidationDocuments')->willReturn(true);
		$doc = new IdDocs();
		$doc->setId(7);
		$doc->setFileId(9);
		$doc->setRejectedAt(new \DateTime('2026-11-09T00:00:00Z'));
		$file = new File();
		$file->setId(9);
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$this->documents->method('getByNodeId')->willReturn($doc);
		$this->files->method('getById')->willReturn($file);
		$this->documents->expects($this->never())->method('rejectIfPending');
		$this->expectException(LibresignException::class);
		$this->service->reject(11, 'Other reason', $this->createMock(IUser::class));
	}

	public function testRejectPersistsDecisionBeforeNotificationFlush(): void {
		$this->policy->method('userCanApproveValidationDocuments')->willReturn(true);
		$approver = $this->createMock(IUser::class);
		$approver->method('getUID')->willReturn('reviewer');
		$doc = new IdDocs();
		$doc->setId(7);
		$doc->setFileId(9);
		$doc->setUserId('account-user');
		$doc->setSignRequestId(null);
		$file = new File();
		$file->setId(9);
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$this->documents->method('getByNodeId')->willReturn($doc);
		$this->files->method('getById')->willReturn($file);
		$this->documents->expects($this->once())->method('rejectIfPending')
			->with($doc, 'reviewer', 'Expired document', $this->isInstanceOf(\DateTime::class))
			->willReturn(true);
		$this->notifications->expects($this->once())->method('flushCommitted');
		$this->service->reject(11, ' Expired document ', $approver);
	}
}
