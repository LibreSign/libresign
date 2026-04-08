<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Process;

use OCA\Libresign\Service\Process\ProcessSignaler;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

class ProcessSignalerTest extends TestCase {
	#[DataProvider('provideInvalidPids')]
	public function testIsRunningReturnsFalseForInvalidPid(int $pid): void {
		$signaler = new ProcessSignaler($this->createMock(LoggerInterface::class));

		$this->assertFalse($signaler->isRunning($pid));
	}

	#[DataProvider('provideInvalidPids')]
	public function testStopPidReturnsFalseForInvalidPid(int $pid): void {
		$signaler = new ProcessSignaler($this->createMock(LoggerInterface::class));

		$this->assertFalse($signaler->stopPid($pid));
	}

	/**
	 * @return array<string, array{0: int}>
	 */
	public static function provideInvalidPids(): array {
		return [
			'zero' => [0],
			'negative' => [-1],
		];
	}
}
