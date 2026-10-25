<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Exception;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\AppFramework\Http;
use PHPUnit\Framework\Attributes\DataProvider;

final class LibresignExceptionTest extends TestCase {
	#[DataProvider('provideLegacyCodes')]
	public function testGetHttpStatusFallsBackToTheCodeWhenNoStatusIsGiven(int $code, int $expected): void {
		$exception = new LibresignException('message', $code);

		$this->assertSame($expected, $exception->getHttpStatus());
	}

	public static function provideLegacyCodes(): array {
		return [
			'no code' => [0, Http::STATUS_UNPROCESSABLE_ENTITY],
			'http status as code' => [Http::STATUS_NOT_FOUND, Http::STATUS_NOT_FOUND],
			'sentinel code' => [1, 1],
		];
	}

	public function testGetHttpStatusUsesTheExplicitStatusInsteadOfTheCode(): void {
		$exception = new LibresignException('message', 4220, httpStatus: Http::STATUS_BAD_REQUEST);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $exception->getHttpStatus());
		$this->assertSame(4220, $exception->getCode());
	}

	public function testGetHttpStatusUsesTheExplicitStatusWhenThereIsNoCode(): void {
		$exception = new LibresignException('message', httpStatus: Http::STATUS_FORBIDDEN);

		$this->assertSame(Http::STATUS_FORBIDDEN, $exception->getHttpStatus());
		$this->assertSame(0, $exception->getCode());
	}

	public function testConstructorKeepsThePreviousException(): void {
		$previous = new \RuntimeException('cause');

		$exception = new LibresignException('message', 1, $previous, Http::STATUS_CONFLICT);

		$this->assertSame($previous, $exception->getPrevious());
	}
}
