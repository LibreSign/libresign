<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Service\SignRequestStatusService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class SignRequestStatusServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private SequentialSigningService&MockObject $sequentialSigningService;
	private FileStatusService&MockObject $fileStatusService;
	private SignRequestStatusService $service;

	public function setUp(): void {
		parent::setUp();
		$this->sequentialSigningService = $this->createMock(SequentialSigningService::class);
		$this->fileStatusService = $this->createMock(FileStatusService::class);
		$this->service = new SignRequestStatusService(
			$this->sequentialSigningService,
			$this->fileStatusService
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
			[SignRequestStatus::ABLE_TO_SIGN, FileEntity::STATUS_ABLE_TO_SIGN, true, true, true],
			[SignRequestStatus::ABLE_TO_SIGN, FileEntity::STATUS_DRAFT, false, true, false],
			[SignRequestStatus::DRAFT, FileEntity::STATUS_ABLE_TO_SIGN, true, false, false],
			[SignRequestStatus::SIGNED, FileEntity::STATUS_ABLE_TO_SIGN, true, false, false],
			[SignRequestStatus::DRAFT, FileEntity::STATUS_DRAFT, false, false, false],
			[SignRequestStatus::ABLE_TO_SIGN, null, false, true, false],
		];
	}

	#[DataProvider('signRequestStatusUpdateScenarios')]
	public function testUpdateStatusIfAllowed(
		SignRequestStatus $current,
		SignRequestStatus $desired,
		bool $isNew,
		bool $isUpgrade,
		bool $shouldUpdate,
	): void {
		$signRequest = $this->createMock(SignRequestEntity::class);

		if (!$isNew) {
			$this->sequentialSigningService
				->expects($this->once())
				->method('isStatusUpgrade')
				->with($current, $desired)
				->willReturn($isUpgrade);
		}

		$signRequest
			->expects($shouldUpdate ? $this->once() : $this->never())
			->method('setStatusEnum')
			->with($desired);

		$this->service->updateStatusIfAllowed($signRequest, $current, $desired, $isNew);
	}

	public static function signRequestStatusUpdateScenarios(): array {
		$draft = SignRequestStatus::DRAFT;
		$able = SignRequestStatus::ABLE_TO_SIGN;
		$signed = SignRequestStatus::SIGNED;

		return [
			[$draft, $able, true, true, true],
			[$draft, $signed, true, true, true],
			[$able, $signed, true, true, true],
			[$draft, $able, false, true, true],
			[$draft, $signed, false, true, true],
			[$able, $signed, false, true, true],
			[$able, $draft, false, false, false],
			[$signed, $draft, false, false, false],
			[$signed, $able, false, false, false],
			[$draft, $draft, false, false, false],
			[$able, $able, false, false, false],
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
		$draft = FileEntity::STATUS_DRAFT;
		$able = FileEntity::STATUS_ABLE_TO_SIGN;
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
}
