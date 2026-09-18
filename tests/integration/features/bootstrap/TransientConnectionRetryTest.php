<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TransientConnectionRetry.php';

final class TransientConnectionRetryTest extends TestCase {
	public function testReturnsOnFirstSuccess(): void {
		$calls = 0;
		$result = TransientConnectionRetry::run(function () use (&$calls): string {
			$calls++;
			return 'ok';
		}, 3, 1);

		$this->assertSame('ok', $result);
		$this->assertSame(1, $calls);
	}

	public function testRetriesConnectExceptionThenSucceeds(): void {
		$calls = 0;
		$result = TransientConnectionRetry::run(function () use (&$calls): string {
			$calls++;
			if ($calls < 3) {
				throw new ConnectException(
					'cURL error 52: Empty reply from server',
					new Request('POST', 'http://localhost/ocs/v2.php/apps/libresign/api/v1/request-signature')
				);
			}
			return 'recovered';
		}, 3, 1);

		$this->assertSame('recovered', $result);
		$this->assertSame(3, $calls);
	}

	public function testRethrowsAfterExhaustingAttempts(): void {
		$calls = 0;
		$this->expectException(ConnectException::class);
		$this->expectExceptionMessage('Empty reply from server');

		try {
			TransientConnectionRetry::run(function () use (&$calls): void {
				$calls++;
				throw new ConnectException(
					'cURL error 52: Empty reply from server',
					new Request('POST', 'http://localhost/test')
				);
			}, 3, 1);
		} finally {
			$this->assertSame(3, $calls);
		}
	}

	public function testDoesNotRetryNonConnectExceptions(): void {
		$calls = 0;
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('boom');

		try {
			TransientConnectionRetry::run(function () use (&$calls): void {
				$calls++;
				throw new RuntimeException('boom');
			}, 3, 1);
		} finally {
			$this->assertSame(1, $calls);
		}
	}
}
