<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Worker\WorkerConfiguration;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class WorkerConfigurationTest extends TestCase {
	private IAppConfig&MockObject $appConfig;

	public function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
	}

	private function makeService(): WorkerConfiguration {
		return new WorkerConfiguration($this->appConfig);
	}

	public function testIsAsyncLocalEnabledRequiresBothConditions(): void {
		$this->appConfig->expects($this->exactly(2))
			->method('getValueString')
			->willReturnMap([
				[Application::APP_ID, 'signing_mode', 'sync', 'async'],
				[Application::APP_ID, 'worker_type', 'local', 'local'],
			]);

		$service = $this->makeService();
		$this->assertTrue($service->isAsyncLocalEnabled());
	}

	public function testIsAsyncLocalEnabledFalseWhenSigningModeNotAsync(): void {
		$this->appConfig->expects($this->once())
			->method('getValueString')
			->with(Application::APP_ID, 'signing_mode', 'sync')
			->willReturn('sync');

		$service = $this->makeService();
		$this->assertFalse($service->isAsyncLocalEnabled());
	}

	public function testIsAsyncLocalEnabledFalseWhenWorkerTypeNotLocal(): void {
		$this->appConfig->expects($this->exactly(2))
			->method('getValueString')
			->willReturnMap([
				[Application::APP_ID, 'signing_mode', 'sync', 'async'],
				[Application::APP_ID, 'worker_type', 'local', 'remote'],
			]);

		$service = $this->makeService();
		$this->assertFalse($service->isAsyncLocalEnabled());
	}

	#[DataProvider('desiredWorkerCountProvider')]
	public function testGetDesiredWorkerCountClampsValue(int $configValue, int $expected): void {
		$this->appConfig->expects($this->once())
			->method('getValueInt')
			->with(Application::APP_ID, 'parallel_workers', 4)
			->willReturn($configValue);

		$service = $this->makeService();
		$this->assertEquals($expected, $service->getDesiredWorkerCount());
	}

	public static function desiredWorkerCountProvider(): array {
		return [
			'below minimum' => [0, 1],
			'at minimum' => [1, 1],
			'default' => [4, 4],
			'normal' => [16, 16],
			'at maximum' => [32, 32],
			'above maximum' => [100, 32],
		];
	}
}
