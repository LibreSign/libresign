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
		$signaler = new class($this->createMock(LoggerInterface::class)) extends ProcessSignaler {
			/** @var array<int, array{pid: int, signal: int}> */
			public array $signals = [];

			protected function sendSignal(int $pid, int $signal): bool {
				$this->signals[] = ['pid' => $pid, 'signal' => $signal];
				return true;
			}
		};

		$this->assertFalse($signaler->stopPid($pid));
		$this->assertSame([], $signaler->signals);
	}

	public function testStopPidDelegatesValidPidToSignalBoundary(): void {
		$signaler = new class($this->createMock(LoggerInterface::class)) extends ProcessSignaler {
			/** @var array<int, array{pid: int, signal: int}> */
			public array $signals = [];

			protected function sendSignal(int $pid, int $signal): bool {
				$this->signals[] = ['pid' => $pid, 'signal' => $signal];
				return true;
			}
		};

		$this->assertTrue($signaler->stopPid(123, SIGKILL));
		$this->assertSame([['pid' => 123, 'signal' => SIGKILL]], $signaler->signals);
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
