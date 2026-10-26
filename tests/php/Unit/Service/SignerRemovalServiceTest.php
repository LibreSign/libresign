<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Events\SignRequestCanceledEvent;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Service\SignerRemovalService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class SignerRemovalServiceTest extends TestCase {
	private FileMapper&MockObject $files;
	private SignRequestMapper&MockObject $requests;
	private IdentifyMethodService&MockObject $identification;
	private FileElementMapper&MockObject $elements;
	private SequentialSigningService&MockObject $sequential;
	private FileStatusService&MockObject $statuses;
	private IEventDispatcher&MockObject $events;
	private IDBConnection&MockObject $db;
	private LoggerInterface&MockObject $logger;
	private SignerRemovalService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->files = $this->createMock(FileMapper::class);
		$this->requests = $this->createMock(SignRequestMapper::class);
		$this->identification = $this->createMock(IdentifyMethodService::class);
		$this->elements = $this->createMock(FileElementMapper::class);
		$this->sequential = $this->createMock(SequentialSigningService::class);
		$this->statuses = $this->createMock(FileStatusService::class);
		$this->events = $this->createMock(IEventDispatcher::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->service = new SignerRemovalService(
			$this->files,
			$this->requests,
			$this->identification,
			$this->elements,
			$this->sequential,
			$this->statuses,
			$this->events,
			$l10n,
			$this->db,
			$this->logger,
		);
	}

	private function file(FileStatus $status = FileStatus::ABLE_TO_SIGN): FileEntity {
		$file = new FileEntity();
		$file->setId(5);
		$file->setUuid('test-file-5');
		$file->setStatus($status->value);
		return $file;
	}

	private function signer(SignRequestStatus $status = SignRequestStatus::ABLE_TO_SIGN): SignRequest {
		$request = new SignRequest();
		$request->setId(9);
		$request->setFileId(5);
		$request->setStatusEnum($status);
		return $request;
	}

	/**
	 * @param array<string, array<IIdentifyMethod>> $methods
	 * @param ?list<SignRequest> $siblings
	 */
	private function givenSigner(FileEntity $file, SignRequest $signer, array $methods = [], ?array $siblings = null): void {
		$this->files->method('getByIdForUpdate')->willReturn($file);
		$this->requests->method('getByFileIdForUpdate')->willReturn($siblings ?? [$signer]);
		$this->identification->method('getIdentifyMethodsFromSignRequestId')->willReturn($methods);
	}

	private function allowPendingRemoval(): void {
		$this->requests->method('deleteIfUnsigned')->willReturn(true);
		$this->elements->method('getByFileIdAndSignRequestId')->willReturn([]);
		$this->sequential->method('activateAfterDeletion')->willReturn([]);
	}

	public function testPendingSignerMayBeRemovedWhileAnotherPendingSignerIsPreserved(): void {
		$file = $this->file();
		$toRemove = $this->signer();
		$other = $this->signer(SignRequestStatus::DRAFT);
		$other->setId(10);
		$this->givenSigner($file, $toRemove, siblings: [$toRemove, $other]);
		$this->requests->method('getByFileId')->willReturn([$other]);
		$this->allowPendingRemoval();

		$this->service->remove(5, 9, notify: false);

		$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $file->getStatus());
		$this->assertSame(SignRequestStatus::DRAFT, $other->getStatusEnum());
	}

	public function testCompletedSignerCannotBeRemoved(): void {
		$file = $this->file(FileStatus::PARTIAL_SIGNED);
		$completed = $this->signer(SignRequestStatus::SIGNED);
		$completed->setSignedHash('existing-signature');
		$this->givenSigner($file, $completed);
		$this->requests->expects($this->never())->method('deleteIfUnsigned');
		$this->events->expects($this->never())->method('dispatchTyped');

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->service->remove(5, 9);
	}

	public function testAnotherCompletedSignerPreventsRemoval(): void {
		$file = $this->file();
		$pending = $this->signer();
		$completed = $this->signer(SignRequestStatus::SIGNED);
		$completed->setId(10);
		$this->givenSigner($file, $pending, siblings: [$pending, $completed]);
		$this->requests->expects($this->never())->method('deleteIfUnsigned');

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->service->remove(5, 9);
	}

	public function testConcurrentCompletionIsNotDiscardedByRemoval(): void {
		$file = $this->file();
		$signer = $this->signer();
		$this->givenSigner($file, $signer);
		$this->requests->method('deleteIfUnsigned')->willReturn(false);
		$this->db->expects($this->once())->method('rollBack');
		$this->events->expects($this->never())->method('dispatchTyped');

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->service->remove(5, 9);
	}

	public function testFailedRemovalRollsBackWithoutNotifying(): void {
		$this->givenSigner($this->file(), $this->signer());
		$this->requests->method('deleteIfUnsigned')->willReturn(true);
		$this->identification->method('deleteBySignRequestId')->willThrowException(new \RuntimeException('Persistence failed'));
		$this->db->expects($this->once())->method('rollBack');
		$this->events->expects($this->never())->method('dispatchTyped');
		$this->sequential->expects($this->never())->method('notifyActivatedSigners');

		$this->expectException(\RuntimeException::class);
		$this->service->remove(5, 9);
	}

	public function testRollbackErrorSurvivesCacheCleanupFailure(): void {
		$this->givenSigner($this->file(), $this->signer());
		$this->requests->method('deleteIfUnsigned')->willReturn(true);
		$this->identification->method('deleteBySignRequestId')
			->willThrowException(new \RuntimeException('Persistence failed'));
		$this->files->method('invalidateCachedEntity')
			->willThrowException(new \RuntimeException('Cache unavailable'));
		$this->db->expects($this->once())->method('rollBack');
		$this->logger->expects($this->once())->method('warning');
		$this->events->expects($this->never())->method('dispatchTyped');

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Persistence failed');
		$this->service->remove(5, 9);
	}

	public function testNotificationFollowsSuccessfulCommit(): void {
		$file = $this->file();
		$method = $this->createMock(IIdentifyMethod::class);
		$this->givenSigner($file, $this->signer(), ['account' => [$method]]);
		$this->allowPendingRemoval();
		$other = $this->signer(SignRequestStatus::DRAFT);
		$other->setId(10);
		$this->requests->method('getByFileId')->willReturn([$other]);

		$committed = false;
		$this->db->method('commit')->willReturnCallback(static function () use (&$committed): void {
			$committed = true;
		});
		$this->events->expects($this->once())->method('dispatchTyped')
			->willReturnCallback(function ($event) use (&$committed): void {
				$this->assertTrue($committed, 'Cancellation must only be published after commit');
				$this->assertInstanceOf(SignRequestCanceledEvent::class, $event);
			});

		$this->service->remove(5, 9);

		$this->assertTrue($committed);
	}

	public function testCacheFailureDoesNotObscureCommittedRemovalOrSkipNotification(): void {
		$method = $this->createMock(IIdentifyMethod::class);
		$this->givenSigner($this->file(), $this->signer(), ['account' => [$method]]);
		$this->allowPendingRemoval();
		$other = $this->signer(SignRequestStatus::DRAFT);
		$other->setId(10);
		$this->requests->method('getByFileId')->willReturn([$other]);
		$this->files->method('invalidateCachedEntity')->willThrowException(new \RuntimeException('Cache offline'));
		$this->logger->expects($this->once())->method('warning');
		$this->statuses->expects($this->once())->method('invalidateCachedStatus');
		$this->events->expects($this->once())->method('dispatchTyped');

		$this->service->remove(5, 9);

		$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $this->file()->getStatus());
	}

	public function testEnvelopeRemovalPreservesSignedChildAndParent(): void {
		$envelope = $this->file();
		$envelope->setNodeType('envelope');
		$child = new FileEntity();
		$child->setId(6);
		$child->setParentFileId(5);
		$child->setStatus(FileStatus::PARTIAL_SIGNED->value);
		$parentRequest = $this->signer();
		$childRequest = new SignRequest();
		$childRequest->setId(10);
		$childRequest->setFileId(6);
		$childRequest->setStatusEnum(SignRequestStatus::SIGNED);

		$this->files->method('getByIdForUpdate')
			->willReturnCallback(static fn (int $id): FileEntity => $id === 5 ? $envelope : $child);
		$this->requests->method('getByFileIdForUpdate')
			->willReturnCallback(static fn (int $id): array => $id === 5 ? [$parentRequest] : [$childRequest]);
		$this->requests->method('getByEnvelopeChildrenAndIdentifyMethod')->willReturn([$childRequest]);
		$this->requests->expects($this->never())->method('deleteIfUnsigned');
		$this->events->expects($this->never())->method('dispatchTyped');

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);
		$this->service->remove(5, 9);
	}
}
