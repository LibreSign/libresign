<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureRejection;

use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\SignatureRejection\CommittedRejection;
use OCA\Libresign\Service\SignatureRejection\RejectionInput;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionNotifier;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionPersistence;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionRules;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignatureRejectionServiceTest extends TestCase {
	use SignatureRejectionSamples;

	private SignatureRejectionRules&MockObject $rules;
	private SignatureRejectionPersistence&MockObject $persistence;
	private SignatureRejectionNotifier&MockObject $notifier;
	private SignatureRejectionService $service;

	protected function setUp(): void {
		$this->rules = $this->createMock(SignatureRejectionRules::class);
		$this->persistence = $this->createMock(SignatureRejectionPersistence::class);
		$this->notifier = $this->createMock(SignatureRejectionNotifier::class);
		$this->service = new SignatureRejectionService($this->rules, $this->persistence, $this->notifier);
	}

	public function testSuccessfulRejectionReturnsSignerAndNotifiesCommittedResult(): void {
		$file = $this->file();
		$signer = $this->signer();
		$input = new RejectionInput('reason', true, false);
		$result = new CommittedRejection(FileStatus::ABLE_TO_SIGN->value, [$this->signer(id: 99)]);
		$this->rules->expects($this->once())->method('validate')
			->with($file, $signer, 'reason', true)->willReturn($input);
		$this->persistence->expects($this->once())->method('commit')
			->with($file, $signer, $input)->willReturn($result);
		$this->notifier->expects($this->once())->method('afterCommit')
			->with($file, $signer, $result);

		$this->assertSame($signer, $this->service->reject($file, $signer, 'reason', true));
	}

	public function testInvalidInputDoesNotWriteOrNotify(): void {
		$this->rules->method('validate')->willThrowException(new LibresignException('not allowed'));
		$this->persistence->expects($this->never())->method('commit');
		$this->notifier->expects($this->never())->method('afterCommit');
		$this->expectException(LibresignException::class);

		$this->service->reject($this->file(), $this->signer());
	}

	public function testFailedWriteNeverDispatchesNotifications(): void {
		$this->rules->method('validate')->willReturn(new RejectionInput(null, false, true));
		$this->persistence->method('commit')->willThrowException(new \RuntimeException('write failure'));
		$this->notifier->expects($this->never())->method('afterCommit');
		$this->expectException(\RuntimeException::class);

		$this->service->reject($this->file(), $this->signer());
	}

	public function testPostCommitNotificationFailureDoesNotChangePersistedSignerState(): void {
		$signer = $this->signer();
		$this->rules->method('validate')->willReturn(new RejectionInput(null, false, true));
		$this->persistence->method('commit')->willReturnCallback(
			static function ($file, $signer): CommittedRejection {
				$signer->setStatusEnum(SignRequestStatus::REJECTED);
				return new CommittedRejection($file->getStatus(), []);
			},
		);
		$this->notifier->method('afterCommit')->willThrowException(new \RuntimeException('external notification failed'));
		$this->expectException(\RuntimeException::class);

		try {
			$this->service->reject($this->file(), $signer);
		} finally {
			$this->assertSame(SignRequestStatus::REJECTED, $signer->getStatusEnum());
		}
	}

	public function testStatusQueriesUseTheEntitiesWithoutInfrastructure(): void {
		$this->assertFalse($this->service->isRejected($this->signer()));
		$this->assertTrue($this->service->isRejected($this->signer(SignRequestStatus::REJECTED->value)));
		$this->assertFalse($this->service->isWorkflowCanceled($this->file()));
		$this->assertTrue($this->service->isWorkflowCanceled($this->file(FileStatus::CANCELED->value)));
	}
}
