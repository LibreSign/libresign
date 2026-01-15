<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\Service\Worker\StartThrottlePolicy;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;

class StartThrottlePolicyTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private ITimeFactory&MockObject $timeFactory;

	public function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
	}

	private function makePolicy(): StartThrottlePolicy {
		return new StartThrottlePolicy($this->appConfig, $this->timeFactory);
	}

	public function testIsThrottledWithInsufficientElapsedTime(): void {
		$this->appConfig->expects($this->once())
			->method('getValueInt')
			->with('libresign', 'worker_last_start_attempt', 0)
			->willReturn(100);

		$this->timeFactory->expects($this->once())
			->method('getTime')
			->willReturn(105);

		$policy = $this->makePolicy();
		$this->assertTrue($policy->isThrottled());
	}

	public function testIsNotThrottledAfterMinInterval(): void {
		$this->appConfig->expects($this->once())
			->method('getValueInt')
			->with('libresign', 'worker_last_start_attempt', 0)
			->willReturn(0);

		$this->timeFactory->expects($this->once())
			->method('getTime')
			->willReturn(15);

		$policy = $this->makePolicy();
		$this->assertFalse($policy->isThrottled());
	}

	public function testIsNotThrottledWhenNoLastAttempt(): void {
		$this->appConfig->expects($this->once())
			->method('getValueInt')
			->with('libresign', 'worker_last_start_attempt', 0)
			->willReturn(0);

		$this->timeFactory->expects($this->once())
			->method('getTime')
			->willReturn(5);

		$policy = $this->makePolicy();
		$this->assertTrue($policy->isThrottled());
	}

	public function testRecordAttemptStoresCurrentTime(): void {
		$this->timeFactory->expects($this->once())
			->method('getTime')
			->willReturn(12345);

		$this->appConfig->expects($this->once())
			->method('setValueInt')
			->with('libresign', 'worker_last_start_attempt', 12345);

		$policy = $this->makePolicy();
		$policy->recordAttempt();
	}
}
