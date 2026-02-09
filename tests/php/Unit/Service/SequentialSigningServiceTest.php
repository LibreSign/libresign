<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class SequentialSigningServiceTest extends TestCase {
	private SignRequestMapper&MockObject $signRequestMapper;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private SequentialSigningService $service;

	public function setUp(): void {
		parent::setUp();
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);

		$this->service = new SequentialSigningService(
			$this->signRequestMapper,
			$this->identifyMethodService
		);
	}

	public function testIsOrderedNumericFlowThrowsExceptionWhenFileNotSet(): void {
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage('File must be set before calling getSignatureFlow(). Call setFile() first.');

		$this->service->isOrderedNumericFlow();
	}

	public static function signatureFlowProvider(): array {
		return [
			'parallel flow' => [SignatureFlow::PARALLEL, false],
			'ordered numeric flow' => [SignatureFlow::ORDERED_NUMERIC, true],
		];
	}

	#[DataProvider('signatureFlowProvider')]
	public function testIsOrderedNumericFlow(SignatureFlow $flow, bool $expected): void {
		$file = $this->createMock(FileEntity::class);
		$file->method('getSignatureFlowEnum')
			->willReturn($flow);

		$this->service->setFile($file);

		$this->assertSame($expected, $this->service->isOrderedNumericFlow());
	}

	public static function parallelOrderProvider(): array {
		return [
			'always returns 1' => [[null, null, 5], [1, 1, 1]],
		];
	}

	public static function orderedNumericOrderProvider(): array {
		return [
			'auto-increment' => [[null, null, null], [1, 2, 3]],
			'user-provided order jumps' => [[null, 5, null], [1, 5, 6]],
		];
	}

	#[DataProvider('parallelOrderProvider')]
	public function testDetermineSigningOrderParallel(array $inputs, array $expected): void {
		$file = $this->createMock(FileEntity::class);
		$file->method('getSignatureFlowEnum')
			->willReturn(SignatureFlow::PARALLEL);

		$this->service->setFile($file);
		$this->service->resetOrderCounter();

		foreach ($inputs as $index => $input) {
			$this->assertEquals($expected[$index], $this->service->determineSigningOrder($input));
		}
	}

	#[DataProvider('orderedNumericOrderProvider')]
	public function testDetermineSigningOrderOrderedNumeric(array $inputs, array $expected): void {
		$file = $this->createMock(FileEntity::class);
		$file->method('getSignatureFlowEnum')
			->willReturn(SignatureFlow::ORDERED_NUMERIC);

		$this->service->setFile($file);
		$this->service->resetOrderCounter();

		foreach ($inputs as $index => $input) {
			$this->assertEquals($expected[$index], $this->service->determineSigningOrder($input));
		}
	}

	public function testResetOrderCounter(): void {
		$file = $this->createMock(FileEntity::class);
		$file->method('getSignatureFlowEnum')
			->willReturn(SignatureFlow::ORDERED_NUMERIC);

		$this->service->setFile($file);

		$this->assertEquals(1, $this->service->determineSigningOrder(null));
		$this->assertEquals(2, $this->service->determineSigningOrder(null));

		$this->service->resetOrderCounter();

		$this->assertEquals(1, $this->service->determineSigningOrder(null));
	}

	public function testReleaseNextOrderSkipsWhenNotOrdered(): void {
		$file = $this->createMock(FileEntity::class);
		$file->method('getSignatureFlowEnum')
			->willReturn(SignatureFlow::PARALLEL);

		$this->service->setFile($file);

		$this->signRequestMapper->expects($this->never())
			->method('getByFileId');

		$this->service->releaseNextOrder(10, 1);
	}

	public static function releaseNextOrderProvider(): array {
		return [
			'order not completed' => [
				[[1, SignRequestStatus::DRAFT, 1], [2, SignRequestStatus::DRAFT, 2]],
				1,
				0,
			],
			'no next order' => [
				[[1, SignRequestStatus::SIGNED, 1]],
				1,
				0,
			],
			'next order already active' => [
				[[1, SignRequestStatus::SIGNED, 1], [2, SignRequestStatus::ABLE_TO_SIGN, 2]],
				1,
				0,
			],
			'activate next order draft signers' => [
				[[1, SignRequestStatus::SIGNED, 1], [2, SignRequestStatus::DRAFT, 2], [3, SignRequestStatus::DRAFT, 2]],
				1,
				2,
			],
			'skip gaps in order sequence' => [
				[[1, SignRequestStatus::SIGNED, 1], [2, SignRequestStatus::DRAFT, 5]],
				1,
				1,
			],
			'activate only draft signers of next order' => [
				[[1, SignRequestStatus::SIGNED, 1], [2, SignRequestStatus::DRAFT, 2], [3, SignRequestStatus::SIGNED, 2]],
				1,
				1,
			],
		];
	}

	#[DataProvider('releaseNextOrderProvider')]
	public function testReleaseNextOrderOrderedFlow(array $requests, int $completedOrder, int $expectedActivations): void {
		$file = $this->createMock(FileEntity::class);
		$file->method('getSignatureFlowEnum')
			->willReturn(SignatureFlow::ORDERED_NUMERIC);
		$this->service->setFile($file);

		$signRequests = $this->buildSignRequests($requests);
		$this->signRequestMapper->expects($this->once())
			->method('getByFileId')
			->with(99)
			->willReturn($signRequests);

		if ($expectedActivations > 0) {
			$this->signRequestMapper->expects($this->exactly($expectedActivations))
				->method('update')
				->with($this->callback(function (SignRequest $request): bool {
					return $request->getStatusEnum() === SignRequestStatus::ABLE_TO_SIGN;
				}));

			$identifyMethod = $this->createMock(\OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod::class);
			$identifyMethod->expects($this->exactly($expectedActivations))
				->method('willNotifyUser')
				->with(true);
			$identifyMethod->expects($this->exactly($expectedActivations))
				->method('notify');

			$this->identifyMethodService->expects($this->exactly($expectedActivations))
				->method('getIdentifyMethodsFromSignRequestId')
				->willReturn([[$identifyMethod]]);
		} else {
			$this->signRequestMapper->expects($this->never())
				->method('update');
			$this->identifyMethodService->expects($this->never())
				->method('getIdentifyMethodsFromSignRequestId');
		}

		$this->service->releaseNextOrder(99, $completedOrder);
	}

	public function testReorderAfterDeletionSkipsWhenNotOrdered(): void {
		$file = $this->createMock(FileEntity::class);
		$file->method('getSignatureFlowEnum')
			->willReturn(SignatureFlow::PARALLEL);

		$this->service->setFile($file);

		$this->signRequestMapper->expects($this->never())
			->method('getByFileId');

		$this->service->reorderAfterDeletion(10, 1);
	}

	public static function reorderAfterDeletionProvider(): array {
		return [
			'deleted order still exists' => [
				[[1, SignRequestStatus::SIGNED, 1], [2, SignRequestStatus::DRAFT, 2]],
				2,
				0,
			],
			'previous order not completed' => [
				[[1, SignRequestStatus::DRAFT, 1], [2, SignRequestStatus::DRAFT, 3]],
				2,
				0,
			],
			'no next order' => [
				[[1, SignRequestStatus::SIGNED, 1]],
				2,
				0,
			],
			'activate next order after deletion' => [
				[[1, SignRequestStatus::SIGNED, 1], [2, SignRequestStatus::DRAFT, 3]],
				2,
				1,
			],
		];
	}

	#[DataProvider('reorderAfterDeletionProvider')]
	public function testReorderAfterDeletionOrderedFlow(array $requests, int $deletedOrder, int $expectedActivations): void {
		$file = $this->createMock(FileEntity::class);
		$file->method('getSignatureFlowEnum')
			->willReturn(SignatureFlow::ORDERED_NUMERIC);
		$this->service->setFile($file);

		$signRequests = $this->buildSignRequests($requests);
		$this->signRequestMapper->expects($this->once())
			->method('getByFileId')
			->with(55)
			->willReturn($signRequests);

		if ($expectedActivations > 0) {
			$this->signRequestMapper->expects($this->exactly($expectedActivations))
				->method('update')
				->with($this->callback(function (SignRequest $request): bool {
					return $request->getStatusEnum() === SignRequestStatus::ABLE_TO_SIGN;
				}));

			$identifyMethod = $this->createMock(\OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod::class);
			$identifyMethod->expects($this->exactly($expectedActivations))
				->method('willNotifyUser')
				->with(true);
			$identifyMethod->expects($this->exactly($expectedActivations))
				->method('notify');

			$this->identifyMethodService->expects($this->exactly($expectedActivations))
				->method('getIdentifyMethodsFromSignRequestId')
				->willReturn([[$identifyMethod]]);
		} else {
			$this->signRequestMapper->expects($this->never())
				->method('update');
			$this->identifyMethodService->expects($this->never())
				->method('getIdentifyMethodsFromSignRequestId');
		}

		$this->service->reorderAfterDeletion(55, $deletedOrder);
	}

	public static function validateStatusByOrderProvider(): array {
		return [
			'parallel flow does not validate order' => [
				SignatureFlow::PARALLEL,
				SignRequestStatus::ABLE_TO_SIGN,
				2,
				[[1, SignRequestStatus::DRAFT, 1]],
				SignRequestStatus::ABLE_TO_SIGN,
			],
			'ordered flow keeps non-able status' => [
				SignatureFlow::ORDERED_NUMERIC,
				SignRequestStatus::DRAFT,
				2,
				[],
				SignRequestStatus::DRAFT,
			],
			'ordered flow allows first signer' => [
				SignatureFlow::ORDERED_NUMERIC,
				SignRequestStatus::ABLE_TO_SIGN,
				1,
				[],
				SignRequestStatus::ABLE_TO_SIGN,
			],
			'ordered flow blocks when lower pending' => [
				SignatureFlow::ORDERED_NUMERIC,
				SignRequestStatus::ABLE_TO_SIGN,
				2,
				[[1, SignRequestStatus::DRAFT, 1], [2, SignRequestStatus::DRAFT, 2]],
				SignRequestStatus::DRAFT,
			],
			'ordered flow allows when lower signed' => [
				SignatureFlow::ORDERED_NUMERIC,
				SignRequestStatus::ABLE_TO_SIGN,
				2,
				[[1, SignRequestStatus::SIGNED, 1], [2, SignRequestStatus::DRAFT, 2]],
				SignRequestStatus::ABLE_TO_SIGN,
			],
		];
	}

	#[DataProvider('validateStatusByOrderProvider')]
	public function testValidateStatusByOrder(
		SignatureFlow $flow,
		SignRequestStatus $desiredStatus,
		int $signingOrder,
		array $requests,
		SignRequestStatus $expected,
	): void {
		$file = $this->createMock(FileEntity::class);
		$file->method('getSignatureFlowEnum')
			->willReturn($flow);
		$this->service->setFile($file);

		$shouldCheckLower = $flow === SignatureFlow::ORDERED_NUMERIC
			&& $desiredStatus === SignRequestStatus::ABLE_TO_SIGN
			&& $signingOrder > 1;

		if ($shouldCheckLower) {
			$this->signRequestMapper->expects($this->once())
				->method('getByFileId')
				->with(200)
				->willReturn($this->buildSignRequests($requests));
		} else {
			$this->signRequestMapper->expects($this->never())
				->method('getByFileId');
		}

		$result = $this->service->validateStatusByOrder(
			$desiredStatus,
			$signingOrder,
			200
		);

		$this->assertSame($expected, $result);
	}

	public static function hasPendingLowerOrderSignersProvider(): array {
		return [
			'no lower orders' => [
				1,
				[],
				false,
			],
			'no pending lower orders' => [
				3,
				[[1, SignRequestStatus::SIGNED, 1], [2, SignRequestStatus::SIGNED, 2]],
				false,
			],
			'has pending lower order draft' => [
				3,
				[[1, SignRequestStatus::SIGNED, 1], [2, SignRequestStatus::DRAFT, 2]],
				true,
			],
			'has pending lower order able to sign' => [
				3,
				[[1, SignRequestStatus::SIGNED, 1], [2, SignRequestStatus::ABLE_TO_SIGN, 2]],
				true,
			],
			'ignores higher orders' => [
				2,
				[[1, SignRequestStatus::SIGNED, 1], [2, SignRequestStatus::DRAFT, 3]],
				false,
			],
		];
	}

	#[DataProvider('hasPendingLowerOrderSignersProvider')]
	public function testHasPendingLowerOrderSigners(int $currentOrder, array $requests, bool $expected): void {
		$file = $this->createMock(FileEntity::class);
		$file->method('getSignatureFlowEnum')
			->willReturn(SignatureFlow::ORDERED_NUMERIC);

		$this->service->setFile($file);

		$signRequests = $this->buildSignRequests($requests);
		$this->signRequestMapper->expects($this->once())
			->method('getByFileId')
			->with(100)
			->willReturn($signRequests);

		$result = $this->service->hasPendingLowerOrderSigners(100, $currentOrder);

		$this->assertSame($expected, $result);
	}

	public static function isStatusUpgradeProvider(): array {
		return [
			'draft to draft is same level' => [SignRequestStatus::DRAFT, SignRequestStatus::DRAFT, true],
			'draft to able to sign is upgrade' => [SignRequestStatus::DRAFT, SignRequestStatus::ABLE_TO_SIGN, true],
			'draft to signed is upgrade' => [SignRequestStatus::DRAFT, SignRequestStatus::SIGNED, true],
			'able to sign to draft is downgrade' => [SignRequestStatus::ABLE_TO_SIGN, SignRequestStatus::DRAFT, false],
			'able to sign to able to sign is same level' => [SignRequestStatus::ABLE_TO_SIGN, SignRequestStatus::ABLE_TO_SIGN, true],
			'able to sign to signed is upgrade' => [SignRequestStatus::ABLE_TO_SIGN, SignRequestStatus::SIGNED, true],
			'signed to draft is downgrade' => [SignRequestStatus::SIGNED, SignRequestStatus::DRAFT, false],
			'signed to able to sign is downgrade' => [SignRequestStatus::SIGNED, SignRequestStatus::ABLE_TO_SIGN, false],
			'signed to signed is same level' => [SignRequestStatus::SIGNED, SignRequestStatus::SIGNED, true],
		];
	}

	#[DataProvider('isStatusUpgradeProvider')]
	public function testIsStatusUpgrade(SignRequestStatus $current, SignRequestStatus $desired, bool $expected): void {
		$file = $this->createMock(FileEntity::class);
		$file->method('getSignatureFlowEnum')
			->willReturn(SignatureFlow::ORDERED_NUMERIC);

		$this->service->setFile($file);

		$result = $this->service->isStatusUpgrade($current, $desired);

		$this->assertSame($expected, $result);
	}

	private function buildSignRequests(array $definitions): array {
		return array_map(
			fn (array $definition): SignRequest => $this->makeSignRequest(
				$definition[0],
				$definition[1],
				$definition[2],
			),
			$definitions,
		);
	}

	private function makeSignRequest(int $id, SignRequestStatus $status, int $order): SignRequest {
		$request = new SignRequest();
		$request->setId($id);
		$request->setStatusEnum($status);
		$request->setSigningOrder($order);
		return $request;
	}
}
