<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignRequest\Error;

use OCA\Libresign\Service\SignRequest\Error\SignRequestErrorReporter;
use OCA\Libresign\Service\SignRequest\ProgressService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class SignRequestErrorReporterTest extends TestCase {
	private ProgressService&MockObject $progressService;
	private LoggerInterface&MockObject $logger;
	private SignRequestErrorReporter $reporter;

	protected function setUp(): void {
		$this->progressService = $this->createMock(ProgressService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->reporter = new SignRequestErrorReporter($this->progressService, $this->logger);
	}

	public function testErrorStoresPayloadAndLogs(): void {
		$exception = new \RuntimeException('boom', 123);
		$context = [
			'exception' => $exception,
			'fileId' => 10,
			'signRequestId' => 20,
			'signRequestUuid' => 'uuid-123',
		];

		$this->progressService->expects($this->once())
			->method('setSignRequestError')
			->with(
				'uuid-123',
				$this->callback(function (array $payload) {
					return $payload['message'] === 'boom'
						&& $payload['code'] === 123
						&& $payload['fileId'] === 10
						&& $payload['signRequestId'] === 20
						&& $payload['signRequestUuid'] === 'uuid-123';
				}),
				300
			);

		$this->progressService->expects($this->once())
			->method('setFileError')
			->with(
				'uuid-123',
				10,
				$this->callback(function (array $payload) {
					return $payload['message'] === 'boom'
						&& $payload['code'] === 123
						&& $payload['fileId'] === 10
						&& $payload['signRequestId'] === 20
						&& $payload['signRequestUuid'] === 'uuid-123';
				}),
				300
			);

		$this->logger->expects($this->once())
			->method('log')
			->with(LogLevel::ERROR, 'Sign failed', $context);

		$this->reporter->error('Sign failed', $context);
	}

	public function testNonErrorDoesNotStorePayload(): void {
		$context = [
			'exception' => new \RuntimeException('warn'),
			'signRequestUuid' => 'uuid-456',
		];

		$this->progressService->expects($this->never())
			->method('setSignRequestError');
		$this->progressService->expects($this->never())
			->method('setFileError');

		$this->logger->expects($this->once())
			->method('log')
			->with(LogLevel::WARNING, 'Warning message', $context);

		$this->reporter->warning('Warning message', $context);
	}

	public function testErrorWithoutExceptionDoesNotStorePayload(): void {
		$context = [
			'signRequestUuid' => 'uuid-789',
		];

		$this->progressService->expects($this->never())
			->method('setSignRequestError');
		$this->progressService->expects($this->never())
			->method('setFileError');

		$this->logger->expects($this->once())
			->method('log')
			->with(LogLevel::ERROR, 'Error without exception', $context);

		$this->reporter->error('Error without exception', $context);
	}

	public function testErrorWithoutUuidDoesNotStorePayload(): void {
		$exception = new \RuntimeException('no uuid');
		$context = [
			'exception' => $exception,
		];

		$this->progressService->expects($this->never())
			->method('setSignRequestError');
		$this->progressService->expects($this->never())
			->method('setFileError');

		$this->logger->expects($this->once())
			->method('log')
			->with(LogLevel::ERROR, 'Error without uuid', $context);

		$this->reporter->error('Error without uuid', $context);
	}

	public function testErrorUsesCustomTtl(): void {
		$exception = new \RuntimeException('ttl', 7);
		$context = [
			'exception' => $exception,
			'signRequestUuid' => 'uuid-ttl',
			'ttl' => 900,
		];

		$this->progressService->expects($this->once())
			->method('setSignRequestError')
			->with(
				'uuid-ttl',
				$this->callback(fn (array $payload) => $payload['message'] === 'ttl'),
				900
			);

		$this->progressService->expects($this->never())
			->method('setFileError');

		$this->logger->expects($this->once())
			->method('log')
			->with(LogLevel::ERROR, 'Error with ttl', $context);

		$this->reporter->error('Error with ttl', $context);
	}
}
