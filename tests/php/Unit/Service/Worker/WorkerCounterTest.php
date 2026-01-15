<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\Service\Worker\WorkerCounter;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class WorkerCounterTest extends TestCase {
	private LoggerInterface&MockObject $logger;

	public function setUp(): void {
		parent::setUp();
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function makeCounter(): WorkerCounter {
		return new WorkerCounter($this->logger);
	}

	public function testCountRunningReturnsNeverNegative(): void {
		$counter = $this->makeCounter();
		$result = $counter->countRunning();
		$this->assertGreaterThanOrEqual(0, $result);
	}
}
