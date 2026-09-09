<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureRejection;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Events\SignatureRejectedEvent;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValue;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionPolicyService;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SignatureRejectionServiceTest extends TestCase {
	private const REJECTED_AT = '2026-09-06T10:00:00+00:00';

	private SignRequestMapper&MockObject $signRequestMapper;
	private FileMapper&MockObject $fileMapper;
	private SignatureRejectionPolicyService&MockObject $rejectionPolicyService;
	private FileStatusService&MockObject $fileStatusService;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private IEventDispatcher&MockObject $eventDispatcher;
	private IDBConnection&MockObject $db;
	private ITimeFactory&MockObject $timeFactory;
	private IL10N&MockObject $l10n;
	private LoggerInterface&MockObject $logger;
	/** @var list<SignRequest> */
	private array $envelopeChildSignRequests = [];
	private ?SignRequest $envelopeSignRequest = null;

	protected function setUp(): void {
		parent::setUp();
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->rejectionPolicyService = $this->createMock(SignatureRejectionPolicyService::class);
		$this->fileStatusService = $this->createMock(FileStatusService::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->l10n->method('t')->willReturnArgument(0);
		$this->timeFactory->method('getDateTime')
			->willReturn(new \DateTime(self::REJECTED_AT));
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')
			->willReturn(['account' => [$this->createMock(IIdentifyMethod::class)]]);

		// Backed by mutable properties so a test can widen the envelope fan-out
		// without the default stub masking it.
		$this->signRequestMapper->method('getByEnvelopeChildrenAndIdentifyMethod')
			->willReturnCallback(fn (): array => $this->envelopeChildSignRequests);
		$this->signRequestMapper->method('getByIdentifyMethodAndFileId')
			->willReturnCallback(function (): SignRequest {
				if (!$this->envelopeSignRequest instanceof SignRequest) {
					throw new DoesNotExistException('the envelope has no signature request for this signer');
				}
				return $this->envelopeSignRequest;
			});
	}

	private function getService(): SignatureRejectionService {
		return new SignatureRejectionService(
			$this->signRequestMapper,
			$this->fileMapper,
			$this->rejectionPolicyService,
			$this->fileStatusService,
			$this->identifyMethodService,
			$this->eventDispatcher,
			$this->db,
			$this->timeFactory,
			$this->l10n,
			$this->logger,
		);
	}

	/** @param array<string, mixed> $policy */
	private function withPolicy(array $policy): void {
		$this->rejectionPolicyService
			->method('getPolicyValue')
			->willReturn(SignatureRejectionPolicyValue::normalize($policy));
	}

	private function file(int $status = FileStatus::ABLE_TO_SIGN->value, ?int $parentFileId = null): File {
		$file = new File();
		$file->setId(10);
		$file->setUserId('requester');
		$file->setStatus($status);
		if ($parentFileId !== null) {
			$file->setParentFileId($parentFileId);
		}
		return $file;
	}

	private function envelope(int $status = FileStatus::ABLE_TO_SIGN->value): File {
		$envelope = $this->file($status);
		$envelope->setId(1);
		$envelope->setNodeType('envelope');
		return $envelope;
	}

	private function signRequest(int $status = SignRequestStatus::ABLE_TO_SIGN->value, int $id = 1): SignRequest {
		$signRequest = new SignRequest();
		$signRequest->setId($id);
		$signRequest->setFileId(10);
		$signRequest->setDisplayName('Signer');
		$signRequest->setStatus($status);
		return $signRequest;
	}

	public function testRejectionIsBlockedWhenThePolicyIsDisabled(): void {
		$this->withPolicy(SignatureRejectionPolicyValue::defaults());
		$this->signRequestMapper->expects($this->never())->method('update');
		$this->db->expects($this->never())->method('beginTransaction');

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Signature rejection is not enabled for this document.');

		$this->getService()->reject($this->file(), $this->signRequest());
	}

	#[DataProvider('provideClosedWorkflowStatuses')]
	public function testRejectionIsBlockedWhenTheWorkflowIsNotOpen(int $fileStatus, string $expectedMessage): void {
		$this->withPolicy(['enabled' => true]);
		$this->signRequestMapper->expects($this->never())->method('update');

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage($expectedMessage);

		$this->getService()->reject($this->file($fileStatus), $this->signRequest());
	}

	/**
	 * @return iterable<string, array{0: int, 1: string}>
	 */
	public static function provideClosedWorkflowStatuses(): iterable {
		yield 'already canceled by a rejection' => [
			FileStatus::CANCELED->value,
			'The signing workflow of this document is already closed.',
		];
		yield 'draft' => [FileStatus::DRAFT->value, 'This document is not open for signature rejection.'];
		yield 'signed' => [FileStatus::SIGNED->value, 'This document is not open for signature rejection.'];
		yield 'deleted' => [FileStatus::DELETED->value, 'This document is not open for signature rejection.'];
	}

	public function testASignerWhoAlreadySignedCannotReject(): void {
		$this->withPolicy(['enabled' => true]);
		$signRequest = $this->signRequest(SignRequestStatus::SIGNED->value);
		$signRequest->setSigned(new \DateTime('2026-09-05T10:00:00+00:00'));

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('File already signed by you');

		$this->getService()->reject($this->file(), $signRequest);
	}

	public function testASignerCannotRejectTwice(): void {
		$this->withPolicy(['enabled' => true]);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('You already rejected this signature request.');

		$this->getService()->reject($this->file(), $this->signRequest(SignRequestStatus::REJECTED->value));
	}

	public function testCommentIsRefusedWhenThePolicyDoesNotAcceptComments(): void {
		$this->withPolicy(['enabled' => true, 'comment_mode' => 'disabled']);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Rejection comments are not allowed for this document.');

		$this->getService()->reject($this->file(), $this->signRequest(), 'I do not agree');
	}

	#[DataProvider('provideMissingRequiredComments')]
	public function testCommentIsRequiredWhenThePolicySaysSo(?string $comment): void {
		$this->withPolicy(['enabled' => true, 'comment_mode' => 'required']);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('A comment is required to reject this signature request.');

		$this->getService()->reject($this->file(), $this->signRequest(), $comment);
	}

	/**
	 * @return iterable<string, array{0: ?string}>
	 */
	public static function provideMissingRequiredComments(): iterable {
		yield 'null' => [null];
		yield 'empty string' => [''];
		yield 'only whitespace' => ["  \n\t "];
	}

	public function testCommentLongerThanTheLimitIsRefused(): void {
		$this->withPolicy(['enabled' => true, 'comment_mode' => 'optional']);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('The rejection comment must have at most %s characters.');

		$this->getService()->reject(
			$this->file(),
			$this->signRequest(),
			str_repeat('a', SignatureRejectionService::MAX_COMMENT_LENGTH + 1),
		);
	}

	public function testCommentAtTheLimitIsAccepted(): void {
		$this->withPolicy(['enabled' => true, 'comment_mode' => 'optional']);
		$comment = str_repeat('a', SignatureRejectionService::MAX_COMMENT_LENGTH);

		$signRequest = $this->getService()->reject($this->file(), $this->signRequest(), $comment);

		$this->assertSame($comment, $signRequest->getRejectionComment());
	}

	public function testTheSignerAlwaysDecidesWhetherTheirCommentIsPrivate(): void {
		// No policy option can take this choice away from the signer.
		$this->withPolicy(['enabled' => true, 'comment_mode' => 'optional']);

		$signRequest = $this->getService()->reject($this->file(), $this->signRequest(), 'Not my document', true);

		$this->assertSame('Not my document', $signRequest->getRejectionComment());
		$this->assertTrue($signRequest->getRejectionCommentPrivate());
	}

	public function testPrivateFlagIsIgnoredWhenThereIsNoComment(): void {
		$this->withPolicy(['enabled' => true, 'comment_mode' => 'optional']);

		$signRequest = $this->getService()->reject($this->file(), $this->signRequest(), null, true);

		$this->assertNull($signRequest->getRejectionComment());
		$this->assertFalse($signRequest->getRejectionCommentPrivate());
	}

	public function testRejectionIsPersistedWithItsTimestampAndComment(): void {
		$this->withPolicy(['enabled' => true, 'comment_mode' => 'optional']);
		$signRequest = $this->signRequest();

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');
		$this->signRequestMapper
			->expects($this->once())
			->method('update')
			->with($signRequest);

		$result = $this->getService()->reject($this->file(), $signRequest, '  I do not agree  ', true);

		$this->assertSame(SignRequestStatus::REJECTED, $result->getStatusEnum());
		$this->assertSame(self::REJECTED_AT, $result->getRejectedAt()?->format(\DateTimeInterface::ATOM));
		$this->assertSame('I do not agree', $result->getRejectionComment());
		$this->assertTrue($result->getRejectionCommentPrivate());
	}

	public function testWorkflowKeepsRunningWhenThePolicyDoesNotCancelIt(): void {
		$this->withPolicy(['enabled' => true, 'cancel_workflow' => false]);
		$file = $this->file();

		$this->fileStatusService->expects($this->never())->method('update');

		$this->getService()->reject($file, $this->signRequest());

		$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $file->getStatus());
	}

	public function testWorkflowIsClosedWhenThePolicyCancelsIt(): void {
		$this->withPolicy(['enabled' => true, 'cancel_workflow' => true]);
		$file = $this->file(FileStatus::PARTIAL_SIGNED->value);

		$this->fileStatusService
			->expects($this->once())
			->method('update')
			->with($file);
		$this->fileStatusService->expects($this->never())->method('propagateStatusToChildren');

		$this->getService()->reject($file, $this->signRequest());

		$this->assertSame(FileStatus::CANCELED->value, $file->getStatus());
		$this->assertTrue($this->getService()->isWorkflowCanceled($file));
	}

	public function testCancellingADocumentClosesTheWholeEnvelope(): void {
		$this->withPolicy(['enabled' => true, 'cancel_workflow' => true]);
		$child = $this->file(FileStatus::ABLE_TO_SIGN->value, parentFileId: 1);
		$envelope = $this->envelope();

		$this->fileMapper->method('getById')->with(1)->willReturn($envelope);
		$this->fileStatusService
			->expects($this->once())
			->method('propagateStatusToChildren')
			->with(1, FileStatus::CANCELED->value);
		$this->fileStatusService->expects($this->exactly(2))->method('update');

		$this->getService()->reject($child, $this->signRequest());

		$this->assertSame(FileStatus::CANCELED->value, $child->getStatus());
		$this->assertSame(FileStatus::CANCELED->value, $envelope->getStatus());
	}

	public function testCancellingAnEnvelopeClosesEveryDocumentItContains(): void {
		$this->withPolicy(['enabled' => true, 'cancel_workflow' => true]);
		$envelope = $this->envelope();

		$this->fileMapper->expects($this->never())->method('getById');
		$this->fileStatusService
			->expects($this->once())
			->method('propagateStatusToChildren')
			->with(1, FileStatus::CANCELED->value);

		$this->getService()->reject($envelope, $this->signRequest());

		$this->assertSame(FileStatus::CANCELED->value, $envelope->getStatus());
	}

	public function testAFailedCancellationLeavesNoPartiallyUpdatedWorkflow(): void {
		$this->withPolicy(['enabled' => true, 'comment_mode' => 'optional', 'cancel_workflow' => true]);
		$file = $this->file();
		$signRequest = $this->signRequest();

		$this->signRequestMapper->expects($this->once())->method('update');
		$this->fileStatusService
			->method('update')
			->willThrowException(new \RuntimeException('database is gone'));

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->never())->method('commit');
		$this->db->expects($this->once())->method('rollBack');
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');
		$this->logger->expects($this->once())->method('error');

		try {
			$this->getService()->reject($file, $signRequest, 'I do not agree');
			$this->fail('Expected the rejection to fail');
		} catch (LibresignException $e) {
			$this->assertSame('It was not possible to register the rejection. Nothing was changed.', $e->getMessage());
		}

		$this->assertSame(SignRequestStatus::ABLE_TO_SIGN, $signRequest->getStatusEnum());
		$this->assertNull($signRequest->getRejectedAt());
		$this->assertNull($signRequest->getRejectionComment());
		$this->assertFalse($signRequest->getRejectionCommentPrivate());
		$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $file->getStatus());
	}

	public function testAFailedSignerUpdateLeavesNoPartiallyUpdatedWorkflow(): void {
		$this->withPolicy(['enabled' => true, 'cancel_workflow' => true]);
		$file = $this->file();
		$signRequest = $this->signRequest();

		$this->signRequestMapper
			->method('update')
			->willThrowException(new \RuntimeException('database is gone'));
		$this->fileStatusService->expects($this->never())->method('update');
		$this->db->expects($this->once())->method('rollBack');

		$this->expectException(LibresignException::class);

		try {
			$this->getService()->reject($file, $signRequest);
		} finally {
			$this->assertSame(SignRequestStatus::ABLE_TO_SIGN, $signRequest->getStatusEnum());
			$this->assertSame(FileStatus::ABLE_TO_SIGN->value, $file->getStatus());
		}
	}

	#[DataProvider('provideWorkflowCancellation')]
	public function testRejectionDispatchesTheEvent(bool $cancelWorkflow): void {
		$this->withPolicy(['enabled' => true, 'cancel_workflow' => $cancelWorkflow]);
		$file = $this->file();
		$signRequest = $this->signRequest();

		$dispatched = null;
		$this->eventDispatcher
			->expects($this->once())
			->method('dispatchTyped')
			->willReturnCallback(function (object $event) use (&$dispatched): void {
				$dispatched = $event;
			});

		$this->getService()->reject($file, $signRequest);

		$this->assertInstanceOf(SignatureRejectedEvent::class, $dispatched);
		$this->assertSame($signRequest, $dispatched->getSignRequest());
		$this->assertSame($file, $dispatched->getLibreSignFile());
		$this->assertSame($cancelWorkflow, $dispatched->wasWorkflowCanceled());
	}

	/**
	 * @return iterable<string, array{0: bool}>
	 */
	public static function provideWorkflowCancellation(): iterable {
		yield 'workflow continues' => [false];
		yield 'workflow canceled' => [true];
	}

	public function testAFailingListenerDoesNotDiscardARecordedRejection(): void {
		$this->withPolicy(['enabled' => true]);
		$signRequest = $this->signRequest();

		$this->db->expects($this->once())->method('commit');
		$this->eventDispatcher
			->method('dispatchTyped')
			->willThrowException(new \RuntimeException('listener exploded'));
		$this->logger->expects($this->once())->method('error');

		$result = $this->getService()->reject($this->file(), $signRequest);

		$this->assertSame(SignRequestStatus::REJECTED, $result->getStatusEnum());
	}

	public function testRejectingAnEnvelopeClosesEverySignatureRequestOfTheSigner(): void {
		// Signing an envelope closes the request of every document it contains plus
		// the one on the envelope itself; a rejection has to close the same set.
		$this->withPolicy(['enabled' => true, 'comment_mode' => 'optional']);
		$envelope = $this->envelope();
		$onDoc1 = $this->signRequest(id: 11);
		$onDoc2 = $this->signRequest(id: 12);
		$onTheEnvelope = $this->signRequest(id: 13);

		$this->envelopeChildSignRequests = [$onDoc1, $onDoc2];
		$this->envelopeSignRequest = $onTheEnvelope;

		$updated = [];
		$this->signRequestMapper
			->expects($this->exactly(3))
			->method('update')
			->willReturnCallback(function (SignRequest $signRequest) use (&$updated): SignRequest {
				$updated[] = $signRequest->getId();
				return $signRequest;
			});

		$this->getService()->reject($envelope, $onDoc1, 'I do not agree with this package');

		$this->assertSame([11, 12, 13], $updated);
		foreach ([$onDoc1, $onDoc2, $onTheEnvelope] as $signRequest) {
			$this->assertSame(SignRequestStatus::REJECTED, $signRequest->getStatusEnum());
			$this->assertSame(self::REJECTED_AT, $signRequest->getRejectedAt()?->format(\DateTimeInterface::ATOM));
			$this->assertSame('I do not agree with this package', $signRequest->getRejectionComment());
		}
	}

	public function testADocumentTheSignerAlreadySignedKeepsItsSignature(): void {
		$this->withPolicy(['enabled' => true]);
		$envelope = $this->envelope();
		$pending = $this->signRequest(id: 11);
		$alreadySigned = $this->signRequest(SignRequestStatus::SIGNED->value, id: 12);
		$alreadySigned->setSigned(new \DateTime('2026-09-05T10:00:00+00:00'));

		$this->envelopeChildSignRequests = [$pending, $alreadySigned];

		$updated = [];
		$this->signRequestMapper
			->method('update')
			->willReturnCallback(function (SignRequest $signRequest) use (&$updated): SignRequest {
				$updated[] = $signRequest->getId();
				return $signRequest;
			});

		$this->getService()->reject($envelope, $pending);

		$this->assertSame([11], $updated);
		$this->assertSame(SignRequestStatus::SIGNED, $alreadySigned->getStatusEnum());
	}

	public function testAPlainRequestNeverLooksForEnvelopeSiblings(): void {
		$this->withPolicy(['enabled' => true]);
		$this->signRequestMapper->expects($this->never())->method('getByEnvelopeChildrenAndIdentifyMethod');
		$this->signRequestMapper->expects($this->never())->method('getByIdentifyMethodAndFileId');

		$this->getService()->reject($this->file(), $this->signRequest());
	}

	public function testIsRejected(): void {
		$service = $this->getService();

		$this->assertFalse($service->isRejected($this->signRequest()));
		$this->assertTrue($service->isRejected($this->signRequest(SignRequestStatus::REJECTED->value)));
	}

	public function testIsWorkflowCanceled(): void {
		$service = $this->getService();

		$this->assertFalse($service->isWorkflowCanceled($this->file()));
		$this->assertTrue($service->isWorkflowCanceled($this->file(FileStatus::CANCELED->value)));
	}
}
