<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\SigningCoordinatorService;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SigningCoordinatorServiceTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private SigningCoordinatorService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->service = new SigningCoordinatorService($this->appConfig);
	}

	public function testShouldUseParallelProcessingReturnsFalseForSingleSigner(): void {
		$this->appConfig->expects($this->never())
			->method('getValueString');

		$this->assertFalse($this->service->shouldUseParallelProcessing(1));
		$this->assertFalse($this->service->shouldUseParallelProcessing(0));
	}

	#[DataProvider('signingModeProvider')]
	public function testShouldUseParallelProcessingReadsConfig(string $mode, bool $expected): void {
		$this->appConfig->expects($this->once())
			->method('getValueString')
			->with(Application::APP_ID, 'signing_mode', 'sync')
			->willReturn($mode);

		$this->assertSame($expected, $this->service->shouldUseParallelProcessing(2));
	}

	public static function signingModeProvider(): array {
		return [
			'async enabled' => ['async', true],
			'sync disabled' => ['sync', false],
			'unknown mode treated as sync' => ['manual', false],
		];
	}
}
