<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\SignatureFlow;
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
}
