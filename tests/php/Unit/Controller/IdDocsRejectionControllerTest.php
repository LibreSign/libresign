<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\IdDocsController;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocs;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdDoc\IdDocRejectionNotifier;
use OCA\Libresign\Service\IdDoc\IdDocRejectionService;
use OCA\Libresign\Service\IdDoc\IdDocsPolicyService;
use OCA\Libresign\Service\IdDoc\IdDocsService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class IdDocsRejectionControllerTest extends TestCase {
	private function newRejectionService(
		?IdDocsPolicyService $policy = null,
		?IdDocsMapper $documents = null,
		?FileMapper $files = null,
		?IdDocRejectionNotifier $notifier = null,
	): IdDocRejectionService {
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getDateTime')->willReturn(new \DateTime('2026-11-21T12:00:00Z'));
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		return new IdDocRejectionService(
			$policy ?? $this->createMock(IdDocsPolicyService::class),
			$documents ?? $this->createMock(IdDocsMapper::class),
			$files ?? $this->createMock(FileMapper::class),
			$clock,
			$l10n,
			$this->createMock(IDBConnection::class),
			$notifier ?? $this->createMock(IdDocRejectionNotifier::class),
		);
	}

	#[DataProvider('rejectionOutcomes')]
	public function testRejectEnforcesAuthorizationAndPreservesAuditState(
		bool $authenticated,
		string $reason,
		bool $alreadyRejected,
		int $expectedStatus,
		array $expectedData,
	): void {
		$user = $authenticated ? $this->createMock(IUser::class) : null;
		if ($user !== null) {
			$user->method('getUID')->willReturn('reviewer-1');
		}
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$policy = $this->createMock(IdDocsPolicyService::class);
		$policy->method('userCanApproveValidationDocuments')->willReturn($authenticated);
		$policy->method('canApproverSignIdDoc')->willReturn(true);

		$shouldLoad = $authenticated && trim($reason) !== '';
		$canReject = $shouldLoad && !$alreadyRejected;
		$document = new IdDocs();
		$document->setId(19);
		$document->setFileId(42);
		if ($alreadyRejected) {
			$document->setRejectedAt(new \DateTime('2026-11-20T12:00:00Z'));
		}
		$file = new File();
		$file->setId(42);
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$documents = $this->createMock(IdDocsMapper::class);
		$documents->expects($shouldLoad ? $this->once() : $this->never())
			->method('getByNodeId')->with(71)->willReturn($document);
		$documents->expects($canReject ? $this->once() : $this->never())
			->method('rejectIfPending')
			->with($document, 'reviewer-1', trim($reason), $this->isInstanceOf(\DateTime::class))
			->willReturn(true);
		$files = $this->createMock(FileMapper::class);
		$files->expects($shouldLoad ? $this->once() : $this->never())
			->method('getById')->with(42)->willReturn($file);
		$notifier = $this->createMock(IdDocRejectionNotifier::class);
		$notifier->expects($canReject ? $this->once() : $this->never())
			->method('queue')->with($document, $file, trim($reason));
		$notifier->expects($canReject ? $this->once() : $this->never())->method('flushCommitted');

		$controller = new IdDocsController(
			$this->createMock(IRequest::class),
			$this->createMock(SignFileService::class),
			$this->createMock(IL10N::class),
			$this->createMock(IdDocsService::class),
			$this->newRejectionService($policy, $documents, $files, $notifier),
			$session,
			$this->createMock(IdentityDocumentValidator::class),
			$this->createMock(LoggerInterface::class),
		);
		$response = $controller->reject(71, $reason);
		$this->assertSame($expectedStatus, $response->getStatus());
		$this->assertSame($expectedData, $response->getData());
	}

	public static function rejectionOutcomes(): array {
		return [
			'authorized reviewer queues rejection' => [
				true, 'Expired ID — please replace this document.', false,
				Http::STATUS_OK, ['rejected' => true],
			],
			'anonymous caller cannot read or reject a document' => [
				false, 'Expired ID', false, Http::STATUS_BAD_REQUEST, ['message' => 'Not allowed'],
			],
			'already rejected document retains audit and cannot be re-rejected' => [
				true, 'Expired ID', true, Http::STATUS_BAD_REQUEST,
				['message' => 'This document is no longer awaiting approval.'],
			],
			'blank explanation cannot reject a document' => [
				true, '  ', false, Http::STATUS_BAD_REQUEST,
				['message' => 'Provide a rejection reason of up to 1024 characters.'],
			],
		];
	}

	public function testRejectDoesNotExposeInfrastructureErrorsToReviewer(): void {
		$reviewer = $this->createMock(IUser::class);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($reviewer);
		$policy = $this->createMock(IdDocsPolicyService::class);
		$policy->method('userCanApproveValidationDocuments')->with($reviewer, false)->willReturn(true);
		$documents = $this->createMock(IdDocsMapper::class);
		$documents->method('getByNodeId')->with(71)
			->willThrowException(new \RuntimeException('SQLSTATE synthetic: internal table name and private context'));
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('error')
			->with('Failed to reject identification document', $this->arrayHasKey('exception'));
		$controller = new IdDocsController(
			$this->createMock(IRequest::class),
			$this->createMock(SignFileService::class),
			$l10n,
			$this->createMock(IdDocsService::class),
			$this->newRejectionService($policy, $documents),
			$session,
			$this->createMock(IdentityDocumentValidator::class),
			$logger,
		);

		$response = $controller->reject(71, 'Expired identification document');
		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame(['message' => 'Unable to reject identification document.'], $response->getData());
	}

	public function testAuthenticatedSignerCannotListAnotherAccountsDocuments(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('actual-signer');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$service = $this->createMock(IdDocsService::class);
		$service->expects($this->once())->method('list')
			->with(['userId' => 'actual-signer'], 2, 20)
			->willReturn(['data' => [], 'pagination' => []]);
		$controller = new IdDocsController(
			$this->createMock(IRequest::class),
			$this->createMock(SignFileService::class),
			$this->createMock(IL10N::class),
			$service,
			$this->newRejectionService(),
			$session,
			$this->createMock(IdentityDocumentValidator::class),
			$this->createMock(LoggerInterface::class),
		);
		$response = $controller->listUnauthenticatedSignerDocuments('another-account', null, 2, 20);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['data' => [], 'pagination' => []], $response->getData());
	}

	public function testRejectedAccountDocumentDeleteReturnsAuditRetentionError(): void {
		$user = $this->createMock(IUser::class);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$service = $this->createMock(IdDocsService::class);
		$service->expects($this->once())->method('deleteIdDoc')->with(71, $user)
			->willThrowException(new LibresignException('Rejected documents are retained for audit purposes.'));
		$controller = new IdDocsController(
			$this->createMock(IRequest::class),
			$this->createMock(SignFileService::class),
			$this->createMock(IL10N::class),
			$service,
			$this->newRejectionService(),
			$session,
			$this->createMock(IdentityDocumentValidator::class),
			$this->createMock(LoggerInterface::class),
		);
		$response = $controller->delete(71);
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(
			['messages' => ['Rejected documents are retained for audit purposes.']],
			$response->getData(),
		);
	}

	public function testAuthenticatedNonReviewerCannotRejectOrReadDocument(): void {
		$user = $this->createMock(IUser::class);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$policy = $this->createMock(IdDocsPolicyService::class);
		$policy->expects($this->once())->method('userCanApproveValidationDocuments')
			->with($user, false)->willReturn(false);
		$documents = $this->createMock(IdDocsMapper::class);
		$documents->expects($this->never())->method('getByNodeId');
		$notifier = $this->createMock(IdDocRejectionNotifier::class);
		$notifier->expects($this->never())->method('queue');
		$controller = new IdDocsController(
			$this->createMock(IRequest::class),
			$this->createMock(SignFileService::class),
			$this->createMock(IL10N::class),
			$this->createMock(IdDocsService::class),
			$this->newRejectionService($policy, $documents, null, $notifier),
			$session,
			$this->createMock(IdentityDocumentValidator::class),
			$this->createMock(LoggerInterface::class),
		);
		$response = $controller->reject(71, 'Expired identification document.');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['message' => 'Not allowed'], $response->getData());
	}

	public function testAuthorizedReviewerCanReadRejectionAuditFields(): void {
		$user = $this->createMock(IUser::class);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$validator = $this->createMock(IdentityDocumentValidator::class);
		$validator->expects($this->once())->method('userCanApproveValidationDocuments')
			->with($user);
		$expected = [
			'data' => [['rejection' => ['reason' => 'Expired ID', 'by' => 'reviewer-1']]],
			'pagination' => [],
		];
		$service = $this->createMock(IdDocsService::class);
		$service->expects($this->once())->method('list')
			->with(
				['userId' => 'signer-1', 'signRequestId' => 77],
				2,
				20,
				['status' => 'ASC'],
				true,
			)
			->willReturn($expected);
		$controller = new IdDocsController(
			$this->createMock(IRequest::class),
			$this->createMock(SignFileService::class),
			$this->createMock(IL10N::class),
			$service,
			$this->newRejectionService(),
			$session,
			$validator,
			$this->createMock(LoggerInterface::class),
		);
		$response = $controller->listToApproval('signer-1', 77, 2, 20, 'status', 'ASC');
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($expected, $response->getData());
	}

	public function testUserWithoutApprovalPermissionCannotReadReviewerAuditDetails(): void {
		$user = $this->createMock(IUser::class);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$validator = $this->createMock(IdentityDocumentValidator::class);
		$validator->expects($this->once())->method('userCanApproveValidationDocuments')
			->with($user)
			->willThrowException(new \RuntimeException('Reviewer permission required'));
		$service = $this->createMock(IdDocsService::class);
		$service->expects($this->never())->method('list');
		$controller = new IdDocsController(
			$this->createMock(IRequest::class),
			$this->createMock(SignFileService::class),
			$this->createMock(IL10N::class),
			$service,
			$this->newRejectionService(),
			$session,
			$validator,
			$this->createMock(LoggerInterface::class),
		);
		$response = $controller->listToApproval();
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['message' => 'Reviewer permission required'], $response->getData());
	}
}
