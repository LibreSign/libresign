<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureRejection;

use OCA\Libresign\Db\File;
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
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SignatureRejectionServiceTest extends TestCase {
	private const REJECTED_AT = '2026-09-06T10:00:00+00:00';

	private SignRequestMapper&MockObject $signRequestMapper;
	private SignatureRejectionPolicyService&MockObject $rejectionPolicyService;
	private FileStatusService&MockObject $fileStatusService;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private IEventDispatcher&MockObject $eventDispatcher;
	private ITimeFactory&MockObject $timeFactory;
	private IL10N&MockObject $l10n;
	private LoggerInterface&MockObject $logger;

	protected function setUp(): void {
		parent::setUp();
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->rejectionPolicyService = $this->createMock(SignatureRejectionPolicyService::class);
		$this->fileStatusService = $this->createMock(FileStatusService::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->l10n->method('t')->willReturnArgument(0);
		$this->timeFactory->method('getDateTime')
			->willReturn(new \DateTime(self::REJECTED_AT));
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')
			->willReturn(['account' => [$this->createMock(IIdentifyMethod::class)]]);
	}

	private function getService(): SignatureRejectionService {
		return new SignatureRejectionService(
			$this->signRequestMapper,
			$this->rejectionPolicyService,
			$this->fileStatusService,
			$this->identifyMethodService,
			$this->eventDispatcher,
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

	private function file(int $status = FileStatus::ABLE_TO_SIGN->value): File {
		$file = new File();
		$file->setUserId('requester');
		$file->setStatus($status);
		return $file;
	}

	private function signRequest(int $status = SignRequestStatus::ABLE_TO_SIGN->value): SignRequest {
		$signRequest = new SignRequest();
		$signRequest->setId(1);
		$signRequest->setFileId(10);
		$signRequest->setDisplayName('Signer');
		$signRequest->setStatus($status);
		return $signRequest;
	}

	public function testRejectionIsBlockedWhenThePolicyIsDisabled(): void {
		$this->withPolicy(SignatureRejectionPolicyValue::defaults());
		$this->signRequestMapper->expects($this->never())->method('update');

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

	public function testPrivateCommentIsRefusedWhenThePolicyDoesNotAllowIt(): void {
		$this->withPolicy(['enabled' => true, 'comment_mode' => 'optional', 'allow_private_comment' => false]);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Private rejection comments are not allowed for this document.');

		$this->getService()->reject($this->file(), $this->signRequest(), 'Not my document', true);
	}

	public function testPrivateFlagIsIgnoredWhenThereIsNoComment(): void {
		$this->withPolicy(['enabled' => true, 'comment_mode' => 'optional', 'allow_private_comment' => false]);

		$signRequest = $this->getService()->reject($this->file(), $this->signRequest(), null, true);

		$this->assertNull($signRequest->getRejectionComment());
		$this->assertFalse($signRequest->getRejectionCommentPrivate());
	}

	public function testRejectionIsPersistedWithItsTimestampAndComment(): void {
		$this->withPolicy(['enabled' => true, 'comment_mode' => 'optional', 'allow_private_comment' => true]);
		$signRequest = $this->signRequest();

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

		$this->getService()->reject($file, $this->signRequest());

		$this->assertSame(FileStatus::CANCELED->value, $file->getStatus());
		$this->assertTrue($this->getService()->isWorkflowCanceled($file));
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

		$this->eventDispatcher
			->method('dispatchTyped')
			->willThrowException(new \RuntimeException('listener exploded'));
		$this->logger->expects($this->once())->method('error');

		$result = $this->getService()->reject($this->file(), $signRequest);

		$this->assertSame(SignRequestStatus::REJECTED, $result->getStatusEnum());
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
