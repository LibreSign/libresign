<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\FileController;
use OCA\Libresign\Exception\LibresignException;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class FileControllerUploadCountTest extends TestCase {
	public static function uploadCountProvider(): array {
		return [
			'legacy request without count' => [null, 2, true],
			'single upload' => ['1', 1, true],
			'two uploads' => ['2', 2, true],
			'integer form value' => [2, 2, true],
			'truncated upload' => ['2', 1, false],
			'unexpected extra file' => ['1', 2, false],
			'invalid string' => ['abc', 2, false],
			'invalid array' => [['2'], 2, false],
			'zero files declared' => ['0', 2, false],
			'leading zero' => ['02', 2, false],
			'integer overflow' => ['999999999999999999999999', 2, false],
		];
	}

	#[DataProvider('uploadCountProvider')]
	public function testExpectedUploadCount(mixed $declaredCount, int $receivedCount, bool $accepted): void {
		$request = $this->createMock(IRequest::class);
		$request->expects($this->once())
			->method('getParam')
			->with('expectedFilesCount')
			->willReturn($declaredCount);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $message): string => $message);

		$reflection = new ReflectionClass(FileController::class);
		/** @var FileController $controller */
		$controller = $reflection->newInstanceWithoutConstructor();
		$reflection->getProperty('request')->setValue($controller, $request);
		$reflection->getProperty('l10n')->setValue($controller, $l10n);

		if (!$accepted) {
			$this->expectException(LibresignException::class);
			$this->expectExceptionMessage('The number of uploaded files does not match the expected count');
		}

		(new ReflectionMethod(FileController::class, 'assertExpectedUploadedFileCount'))
			->invoke($controller, $receivedCount);
	}
}
