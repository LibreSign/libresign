<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Process;

use OCA\Libresign\Service\Process\ListeningPidResolver;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ListeningPidResolverTest extends TestCase {
	#[DataProvider('provideInvalidPorts')]
	public function testFindListeningPidsRejectsInvalidPort(int $port): void {
		$resolver = new ListeningPidResolver();

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Invalid port');

		$resolver->findListeningPids($port);
	}

	/**
	 * @return array<string, array{0: int}>
	 */
	public static function provideInvalidPorts(): array {
		return [
			'zero' => [0],
			'negative' => [-1],
		];
	}

	public function testFindListeningPidsMergesUniqueFromAvailableStrategies(): void {
		$resolver = new class() extends ListeningPidResolver {
			protected function findListeningPidsUsingSs(int $port): ?array {
				return [100, 101];
			}

			protected function findListeningPidsUsingLsof(int $port): ?array {
				return [101, 102];
			}

			protected function findListeningPidsUsingProc(int $port): ?array {
				return [102, 103];
			}
		};

		$actual = $resolver->findListeningPids(8888);
		sort($actual);

		$this->assertSame([100, 101, 102, 103], $actual);
	}

	public function testFindListeningPidsThrowsWhenNoStrategyIsAvailable(): void {
		$resolver = new class() extends ListeningPidResolver {
			protected function findListeningPidsUsingSs(int $port): ?array {
				return null;
			}

			protected function findListeningPidsUsingLsof(int $port): ?array {
				return null;
			}

			protected function findListeningPidsUsingProc(int $port): ?array {
				return null;
			}
		};

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('no strategy available');

		$resolver->findListeningPids(8888);
	}
}
