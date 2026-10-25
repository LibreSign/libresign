<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Policy\Provider\Worker\WorkerConfigPolicy;
use OCA\Libresign\Service\Worker\WorkerConfiguration;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class WorkerConfigurationTest extends \PHPUnit\Framework\TestCase {
	private IAppConfig&MockObject $appConfig;
	private WorkerConfigPolicy $workerConfigPolicy;

	public function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->workerConfigPolicy = new WorkerConfigPolicy();
	}

	private function makeService(): WorkerConfiguration {
		return new WorkerConfiguration($this->appConfig, $this->workerConfigPolicy);
	}

	private function encodeConfig(string $workerType, int $parallelWorkers): string {
		return json_encode(['worker_type' => $workerType, 'parallel_workers' => $parallelWorkers]);
	}

	#[DataProvider('asyncSigningModes')]
	public function testAsyncSigningModeDoesNotDependOnWorkerType(string $mode, bool $expected): void {
		$this->appConfig->expects($this->once())
			->method('getValueString')
			->with(Application::APP_ID, 'signing_mode', 'sync')
			->willReturn($mode);

		$this->assertSame($expected, $this->makeService()->isAsyncSigningEnabled());
	}

	public static function asyncSigningModes(): array {
		return ['async' => ['async', true], 'sync' => ['sync', false]];
	}

	public function testAsyncLocalIsDisabledForDedicatedWorker(): void {
		$this->appConfig->method('getValueString')->willReturnCallback(static fn (string $app, string $key): string => $key === 'signing_mode' ? 'async' : '{"worker_type":"external","parallel_workers":4}');
		$this->assertFalse($this->makeService()->isAsyncLocalEnabled());
	}

	public function testSigningJobClassesAreExplicit(): void {
		$this->assertSame([
			\OCA\Libresign\BackgroundJob\SignFileJob::class,
			\OCA\Libresign\BackgroundJob\SignSingleFileJob::class,
		], $this->makeService()->getSigningJobClasses());
	}

	#[DataProvider('desiredWorkerCountProvider')]
	public function testGetDesiredWorkerCountClampsValue(int $configValue, int $expected): void {
		$this->appConfig->expects($this->once())
			->method('getValueString')
			->with(Application::APP_ID, WorkerConfigPolicy::SYSTEM_APP_CONFIG_KEY, '')
			->willReturn($this->encodeConfig('local', $configValue));

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

	public function testGetDesiredWorkerCountUsesDefaultWhenConfigEmpty(): void {
		$this->appConfig->expects($this->once())
			->method('getValueString')
			->with(Application::APP_ID, WorkerConfigPolicy::SYSTEM_APP_CONFIG_KEY, '')
			->willReturn('');

		$service = $this->makeService();
		$this->assertEquals(4, $service->getDesiredWorkerCount());
	}
}
