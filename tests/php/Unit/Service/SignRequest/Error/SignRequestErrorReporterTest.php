<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignRequest\Error;

use OCA\Libresign\Service\SignRequest\Error\SignRequestErrorReporter;
use OCA\Libresign\Service\SignRequest\ProgressService;
use PHPUnit\Framework\Attributes\DataProvider;
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

	public static function logLevelDataProvider(): array {
		return [
			'debug level' => [LogLevel::DEBUG, false],
			'info level' => [LogLevel::INFO, false],
			'notice level' => [LogLevel::NOTICE, false],
			'warning level' => [LogLevel::WARNING, false],
			'error level' => [LogLevel::ERROR, true],
			'critical level' => [LogLevel::CRITICAL, false],
			'alert level' => [LogLevel::ALERT, false],
			'emergency level' => [LogLevel::EMERGENCY, false],
		];
	}

	#[DataProvider('logLevelDataProvider')]
	public function testOnlyErrorLevelStoresPayload(string $logLevel, bool $shouldStore): void {
		$exception = new \RuntimeException('Test exception', 500);
		$context = [
			'exception' => $exception,
			'signRequestUuid' => 'uuid-loglevel',
			'fileId' => 42,
		];

		if ($shouldStore) {
			$this->progressService->expects($this->once())
				->method('setSignRequestError');
			$this->progressService->expects($this->once())
				->method('setFileError');
		} else {
			$this->progressService->expects($this->never())
				->method('setSignRequestError');
			$this->progressService->expects($this->never())
				->method('setFileError');
		}

		$this->logger->expects($this->once())
			->method('log')
			->with($logLevel, 'Test message', $context);

		$this->reporter->log($logLevel, 'Test message', $context);
	}

	public static function contextVariationsDataProvider(): array {
		return [
			'complete context with all IDs' => [
				'context' => [
					'exception' => new \RuntimeException('Complete', 100),
					'signRequestUuid' => 'uuid-complete',
					'fileId' => 10,
					'signRequestId' => 20,
					'ttl' => 600,
				],
				'expectSignRequestError' => true,
				'expectFileError' => true,
				'expectedTtl' => 600,
			],
			'context without fileId' => [
				'context' => [
					'exception' => new \RuntimeException('No fileId', 101),
					'signRequestUuid' => 'uuid-nofile',
					'signRequestId' => 25,
				],
				'expectSignRequestError' => true,
				'expectFileError' => false,
				'expectedTtl' => 300, // default
			],
			'context with string numeric fileId' => [
				'context' => [
					'exception' => new \RuntimeException('String fileId', 102),
					'signRequestUuid' => 'uuid-stringfile',
					'fileId' => '15',
					'signRequestId' => '30',
				],
				'expectSignRequestError' => true,
				'expectFileError' => true,
				'expectedTtl' => 300,
			],
			'context with non-numeric fileId' => [
				'context' => [
					'exception' => new \RuntimeException('Invalid fileId', 103),
					'signRequestUuid' => 'uuid-invalidfile',
					'fileId' => 'not-a-number',
					'signRequestId' => 35,
				],
				'expectSignRequestError' => true,
				'expectFileError' => false,
				'expectedTtl' => 300,
			],
			'context with zero TTL' => [
				'context' => [
					'exception' => new \RuntimeException('Zero TTL', 104),
					'signRequestUuid' => 'uuid-zerottl',
					'fileId' => 20,
					'ttl' => 0,
				],
				'expectSignRequestError' => true,
				'expectFileError' => true,
				'expectedTtl' => 0,
			],
			'context with empty string uuid (should not store)' => [
				'context' => [
					'exception' => new \RuntimeException('Empty uuid', 105),
					'signRequestUuid' => '',
					'fileId' => 25,
				],
				'expectSignRequestError' => false,
				'expectFileError' => false,
				'expectedTtl' => 300,
			],
		];
	}

	#[DataProvider('contextVariationsDataProvider')]
	public function testErrorWithVariousContextCombinations(
		array $context,
		bool $expectSignRequestError,
		bool $expectFileError,
		int $expectedTtl,
	): void {
		if ($expectSignRequestError) {
			$this->progressService->expects($this->once())
				->method('setSignRequestError')
				->with(
					$context['signRequestUuid'],
					$this->callback(function (array $payload) use ($context) {
						return $payload['message'] === $context['exception']->getMessage()
							&& $payload['code'] === $context['exception']->getCode();
					}),
					$expectedTtl
				);
		} else {
			$this->progressService->expects($this->never())
				->method('setSignRequestError');
		}

		if ($expectFileError) {
			$fileId = is_numeric($context['fileId']) ? (int)$context['fileId'] : null;
			$this->progressService->expects($this->once())
				->method('setFileError')
				->with(
					$context['signRequestUuid'],
					$fileId,
					$this->isType('array'),
					$expectedTtl
				);
		} else {
			$this->progressService->expects($this->never())
				->method('setFileError');
		}

		$this->logger->expects($this->once())
			->method('log');

		$this->reporter->error('Test error', $context);
	}

	public static function exceptionTypesDataProvider(): array {
		return [
			'RuntimeException' => [
				'exception' => new \RuntimeException('Runtime error', 500),
				'expectedMessage' => 'Runtime error',
				'expectedCode' => 500,
			],
			'InvalidArgumentException' => [
				'exception' => new \InvalidArgumentException('Invalid argument', 400),
				'expectedMessage' => 'Invalid argument',
				'expectedCode' => 400,
			],
			'LogicException' => [
				'exception' => new \LogicException('Logic error', 422),
				'expectedMessage' => 'Logic error',
				'expectedCode' => 422,
			],
			'Exception with zero code' => [
				'exception' => new \Exception('Zero code error', 0),
				'expectedMessage' => 'Zero code error',
				'expectedCode' => 0,
			],
			'Exception with empty message' => [
				'exception' => new \Exception('', 999),
				'expectedMessage' => '',
				'expectedCode' => 999,
			],
		];
	}

	#[DataProvider('exceptionTypesDataProvider')]
	public function testErrorWithDifferentExceptionTypes(
		\Throwable $exception,
		string $expectedMessage,
		int $expectedCode,
	): void {
		$context = [
			'exception' => $exception,
			'signRequestUuid' => 'uuid-exception-types',
			'fileId' => 100,
		];

		$this->progressService->expects($this->once())
			->method('setSignRequestError')
			->with(
				'uuid-exception-types',
				$this->callback(function (array $payload) use ($expectedMessage, $expectedCode) {
					return $payload['message'] === $expectedMessage
						&& $payload['code'] === $expectedCode
						&& isset($payload['timestamp'])
						&& isset($payload['fileId'])
						&& $payload['fileId'] === 100;
				}),
				300
			);

		$this->progressService->expects($this->once())
			->method('setFileError')
			->with(
				'uuid-exception-types',
				100,
				$this->callback(function (array $payload) use ($expectedMessage, $expectedCode) {
					return $payload['message'] === $expectedMessage
						&& $payload['code'] === $expectedCode;
				}),
				300
			);

		$this->logger->expects($this->once())
			->method('log')
			->with(LogLevel::ERROR, 'Exception test', $context);

		$this->reporter->error('Exception test', $context);
	}

	public static function missingRequiredFieldsDataProvider(): array {
		return [
			'missing exception' => [
				'context' => [
					'signRequestUuid' => 'uuid-no-exception',
					'fileId' => 50,
				],
				'shouldStore' => false,
			],
			'missing uuid' => [
				'context' => [
					'exception' => new \RuntimeException('No UUID'),
					'fileId' => 50,
				],
				'shouldStore' => false,
			],
			'null exception' => [
				'context' => [
					'exception' => null,
					'signRequestUuid' => 'uuid-null-exception',
					'fileId' => 50,
				],
				'shouldStore' => false,
			],
			'null uuid' => [
				'context' => [
					'exception' => new \RuntimeException('Null UUID'),
					'signRequestUuid' => null,
					'fileId' => 50,
				],
				'shouldStore' => false,
			],
			'non-throwable exception' => [
				'context' => [
					'exception' => 'not an exception',
					'signRequestUuid' => 'uuid-invalid-exception',
					'fileId' => 50,
				],
				'shouldStore' => false,
			],
		];
	}

	#[DataProvider('missingRequiredFieldsDataProvider')]
	public function testErrorDoesNotStoreWhenRequiredFieldsMissing(
		array $context,
		bool $shouldStore,
	): void {
		if ($shouldStore) {
			$this->progressService->expects($this->atLeastOnce())
				->method('setSignRequestError');
		} else {
			$this->progressService->expects($this->never())
				->method('setSignRequestError');
			$this->progressService->expects($this->never())
				->method('setFileError');
		}

		$this->logger->expects($this->once())
			->method('log')
			->with(LogLevel::ERROR, 'Test error', $context);

		$this->reporter->error('Test error', $context);
	}
}
