<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Service\SignRequest;

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Service\SignRequest\StatusCacheService;
use OCA\Libresign\Service\SignRequest\StatusService;
use OCA\Libresign\Service\SignRequest\StatusUpdatePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class StatusServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private SequentialSigningService&MockObject $sequentialSigningService;
	private FileStatusService&MockObject $fileStatusService;
	private StatusCacheService&MockObject $statusCacheService;
	private StatusUpdatePolicy $statusUpdatePolicy;
	private StatusService $service;

	public function setUp(): void {
		parent::setUp();
		$this->sequentialSigningService = $this->createMock(SequentialSigningService::class);
		$this->fileStatusService = $this->createMock(FileStatusService::class);
		$this->statusCacheService = $this->createMock(StatusCacheService::class);
		$this->statusUpdatePolicy = new StatusUpdatePolicy();
		$this->service = new StatusService(
			$this->sequentialSigningService,
			$this->fileStatusService,
			$this->statusCacheService,
			$this->statusUpdatePolicy
		);
	}

	#[DataProvider('signRequestStatusNotificationScenarios')]
	public function testCanNotifySignRequest(SignRequestStatus $status, bool $canNotify): void {
		$this->assertSame($canNotify, $this->service->canNotifySignRequest($status));
	}

	public static function signRequestStatusNotificationScenarios(): array {
		return [
			[SignRequestStatus::DRAFT, false],
			[SignRequestStatus::ABLE_TO_SIGN, true],
			[SignRequestStatus::SIGNED, false],
		];
	}

	#[DataProvider('shouldNotifyScenarios')]
	public function testShouldNotifySignRequest(
		SignRequestStatus $signRequestStatus,
		?int $fileStatus,
		bool $fileCanNotify,
		bool $signRequestCanNotify,
		bool $expected,
	): void {
		$this->fileStatusService
			->expects($this->once())
			->method('canNotifySigners')
			->with($fileStatus)
			->willReturn($fileCanNotify);

		$result = $this->service->shouldNotifySignRequest($signRequestStatus, $fileStatus);
		$this->assertSame($expected, $result);
	}

	public static function shouldNotifyScenarios(): array {
		return [
			[SignRequestStatus::ABLE_TO_SIGN, FileStatus::ABLE_TO_SIGN->value, true, true, true],
			[SignRequestStatus::ABLE_TO_SIGN, FileStatus::DRAFT->value, false, true, false],
			[SignRequestStatus::DRAFT, FileStatus::ABLE_TO_SIGN->value, true, false, false],
			[SignRequestStatus::SIGNED, FileStatus::ABLE_TO_SIGN->value, true, false, false],
			[SignRequestStatus::DRAFT, FileStatus::DRAFT->value, false, false, false],
			[SignRequestStatus::ABLE_TO_SIGN, null, false, true, false],
		];
	}

	#[DataProvider('initialStatusScenarios')]
	public function testDetermineInitialStatus(
		int $order,
		int $fileId,
		?int $fileStatus,
		?int $signerStatus,
		?SignRequestStatus $currentStatus,
		bool $isOrdered,
		SignRequestStatus $expected,
	): void {
		$this->sequentialSigningService->method('isOrderedNumericFlow')->willReturn($isOrdered);

		if ($signerStatus !== null && $currentStatus !== null) {
			$desired = SignRequestStatus::from($signerStatus);
			$this->sequentialSigningService
				->method('isStatusUpgrade')
				->with($currentStatus, $desired)
				->willReturn($expected !== $currentStatus);
		}

		if ($signerStatus !== null) {
			$desired = SignRequestStatus::from($signerStatus);
			$this->sequentialSigningService
				->method('validateStatusByOrder')
				->with($desired, $order, $fileId)
				->willReturn($expected);
		}

		$result = $this->service->determineInitialStatus($order, $fileId, $fileStatus, $signerStatus, $currentStatus);
		$this->assertSame($expected, $result);
	}

	public static function initialStatusScenarios(): array {
		$draft = FileStatus::DRAFT->value;
		$able = FileStatus::ABLE_TO_SIGN->value;
		$draftStatus = SignRequestStatus::DRAFT;
		$ableStatus = SignRequestStatus::ABLE_TO_SIGN;
		$signedStatus = SignRequestStatus::SIGNED;

		return [
			[1, 123, $draft, null, null, false, $draftStatus],
			[1, 123, $draft, null, null, true, $draftStatus],
			[2, 123, $draft, null, null, false, $draftStatus],
			[2, 123, $draft, null, null, true, $draftStatus],
			[1, 123, $draft, $ableStatus->value, null, false, $draftStatus],
			[1, 123, $draft, $ableStatus->value, null, true, $draftStatus],

			[1, 123, $able, null, null, false, $ableStatus],
			[2, 123, $able, null, null, false, $ableStatus],
			[3, 123, $able, null, null, false, $ableStatus],
			[1, 123, $able, null, null, true, $ableStatus],
			[2, 123, $able, null, null, true, $draftStatus],
			[3, 123, $able, null, null, true, $draftStatus],
			[10, 123, $able, null, null, true, $draftStatus],
			[1, 123, $able, $draftStatus->value, null, false, $ableStatus],
			[2, 123, $able, $draftStatus->value, null, false, $ableStatus],
			[1, 123, $able, $draftStatus->value, null, true, $ableStatus],
			[2, 123, $able, $draftStatus->value, null, true, $draftStatus],

			[1, 123, null, $ableStatus->value, null, false, $ableStatus],
			[1, 123, null, $ableStatus->value, null, true, $ableStatus],
			[1, 123, null, $draftStatus->value, null, false, $draftStatus],
			[1, 123, null, $signedStatus->value, null, false, $signedStatus],
			[2, 123, null, $ableStatus->value, null, true, $ableStatus],
			[2, 123, null, $ableStatus->value, null, false, $ableStatus],

			[1, 123, null, $ableStatus->value, $draftStatus, false, $ableStatus],
			[1, 123, null, $draftStatus->value, $ableStatus, false, $ableStatus],
			[1, 123, null, $ableStatus->value, $ableStatus, false, $ableStatus],

			[1, 123, null, null, null, false, $ableStatus],
			[2, 123, null, null, null, false, $ableStatus],
			[10, 123, null, null, null, false, $ableStatus],
			[1, 123, null, null, null, true, $ableStatus],
			[2, 123, null, null, null, true, $draftStatus],
			[3, 123, null, null, null, true, $draftStatus],
			[10, 123, null, null, null, true, $draftStatus],
		];
	}

	public static function updateStatusIfAllowedScenarios(): array {
		return [
			'keeps status when policy blocks' => [false, SignRequestStatus::ABLE_TO_SIGN],
			'downgrades when pending lower signers' => [true, SignRequestStatus::DRAFT],
		];
	}

	#[DataProvider('updateStatusIfAllowedScenarios')]
	public function testUpdateStatusIfAllowed(bool $hasPendingLowerSigners, SignRequestStatus $expectedStatus): void {
		$signRequest = new SignRequestEntity();
		$signRequest->setStatusEnum(SignRequestStatus::ABLE_TO_SIGN);
		$signRequest->setSigningOrder(2);
		$signRequest->setFileId(10);

		$this->sequentialSigningService->method('isOrderedNumericFlow')->willReturn(true);
		$this->sequentialSigningService->method('isStatusUpgrade')->willReturn(false);
		$this->sequentialSigningService->method('hasPendingLowerOrderSigners')->willReturn($hasPendingLowerSigners);

		$this->service->updateStatusIfAllowed(
			$signRequest,
			SignRequestStatus::ABLE_TO_SIGN,
			SignRequestStatus::DRAFT,
			false
		);

		$this->assertSame($expectedStatus, $signRequest->getStatusEnum());
	}
}
