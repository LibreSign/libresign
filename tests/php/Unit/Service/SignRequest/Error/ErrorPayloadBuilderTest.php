<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignRequest\Error;

use OCA\Libresign\Service\SignRequest\Error\ErrorPayloadBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ErrorPayloadBuilderTest extends TestCase {
	public function testBuildMinimalPayload(): void {
		$builder = new ErrorPayloadBuilder();
		$payload = $builder
			->setMessage('Test error')
			->setCode(500)
			->build();

		$this->assertArrayHasKey('message', $payload);
		$this->assertArrayHasKey('code', $payload);
		$this->assertArrayHasKey('timestamp', $payload);
		$this->assertEquals('Test error', $payload['message']);
		$this->assertEquals(500, $payload['code']);
	}

	public static function buildWithContextDataProvider(): array {
		return [
			'with file only' => [
				'fileId' => 123,
				'signRequestId' => null,
				'signRequestUuid' => null,
				'expectedKeys' => ['fileId'],
				'notExpectedKeys' => ['signRequestId', 'signRequestUuid'],
			],
			'with sign request only' => [
				'fileId' => null,
				'signRequestId' => 456,
				'signRequestUuid' => 'test-uuid-123',
				'expectedKeys' => ['signRequestId', 'signRequestUuid'],
				'notExpectedKeys' => ['fileId'],
			],
			'with file and sign request' => [
				'fileId' => 789,
				'signRequestId' => 456,
				'signRequestUuid' => 'test-uuid-456',
				'expectedKeys' => ['fileId', 'signRequestId', 'signRequestUuid'],
				'notExpectedKeys' => [],
			],
			'with null values' => [
				'fileId' => null,
				'signRequestId' => null,
				'signRequestUuid' => null,
				'expectedKeys' => [],
				'notExpectedKeys' => ['fileId', 'signRequestId', 'signRequestUuid'],
			],
		];
	}

	#[DataProvider('buildWithContextDataProvider')]
	public function testBuildWithContext(
		?int $fileId,
		?int $signRequestId,
		?string $signRequestUuid,
		array $expectedKeys,
		array $notExpectedKeys,
	): void {
		$builder = new ErrorPayloadBuilder();
		$payload = $builder
			->setMessage('Test error')
			->setCode(400)
			->setFileId($fileId)
			->setSignRequestId($signRequestId)
			->setSignRequestUuid($signRequestUuid)
			->build();

		foreach ($expectedKeys as $key) {
			$this->assertArrayHasKey($key, $payload);
			if ($key === 'fileId') {
				$this->assertEquals($fileId, $payload[$key]);
			} elseif ($key === 'signRequestId') {
				$this->assertEquals($signRequestId, $payload[$key]);
			} elseif ($key === 'signRequestUuid') {
				$this->assertEquals($signRequestUuid, $payload[$key]);
			}
		}

		foreach ($notExpectedKeys as $key) {
			$this->assertArrayNotHasKey($key, $payload);
		}
	}

	public function testAddFileError(): void {
		$builder = new ErrorPayloadBuilder();
		$exception1 = new \Exception('File 1 error', 101);
		$exception2 = new \Exception('File 2 error', 102);

		$payload = $builder
			->setMessage('Multi-file error')
			->addFileError(1001, $exception1)
			->addFileError(1002, $exception2)
			->build();

		$this->assertArrayHasKey('fileErrors', $payload);
		$this->assertCount(2, $payload['fileErrors']);
		$this->assertEquals('File 1 error', $payload['fileErrors'][1001]['message']);
		$this->assertEquals(101, $payload['fileErrors'][1001]['code']);
		$this->assertEquals('File 2 error', $payload['fileErrors'][1002]['message']);
		$this->assertEquals(102, $payload['fileErrors'][1002]['code']);
	}

	public function testClearFileErrors(): void {
		$builder = new ErrorPayloadBuilder();
		$exception = new \Exception('Test error');

		$builder->addFileError(1001, $exception);
		$payload1 = $builder->build();
		$this->assertArrayHasKey('fileErrors', $payload1);

		$builder->clearFileErrors();
		$payload2 = $builder->build();
		$this->assertArrayNotHasKey('fileErrors', $payload2);
	}

	public static function fromExceptionDataProvider(): array {
		return [
			'with full context' => [
				'message' => 'Exception message',
				'code' => 789,
				'fileId' => 555,
				'signRequestId' => 666,
				'signRequestUuid' => 'exception-uuid',
				'expectedKeys' => ['message', 'code', 'fileId', 'signRequestId', 'signRequestUuid'],
			],
			'without context' => [
				'message' => 'Standalone error',
				'code' => 999,
				'fileId' => null,
				'signRequestId' => null,
				'signRequestUuid' => null,
				'expectedKeys' => ['message', 'code'],
			],
			'with partial context (file only)' => [
				'message' => 'File error',
				'code' => 400,
				'fileId' => 123,
				'signRequestId' => null,
				'signRequestUuid' => null,
				'expectedKeys' => ['message', 'code', 'fileId'],
			],
		];
	}

	#[DataProvider('fromExceptionDataProvider')]
	public function testFromException(
		string $message,
		int $code,
		?int $fileId,
		?int $signRequestId,
		?string $signRequestUuid,
		array $expectedKeys,
	): void {
		$exception = new \Exception($message, $code);

		$payload = ErrorPayloadBuilder::fromException($exception, $fileId, $signRequestId, $signRequestUuid)->build();

		$this->assertEquals($message, $payload['message']);
		$this->assertEquals($code, $payload['code']);

		foreach ($expectedKeys as $key) {
			$this->assertArrayHasKey($key, $payload);
		}

		if ($fileId !== null) {
			$this->assertEquals($fileId, $payload['fileId']);
		} else {
			$this->assertArrayNotHasKey('fileId', $payload);
		}

		if ($signRequestId !== null) {
			$this->assertEquals($signRequestId, $payload['signRequestId']);
		} else {
			$this->assertArrayNotHasKey('signRequestId', $payload);
		}

		if ($signRequestUuid !== null) {
			$this->assertEquals($signRequestUuid, $payload['signRequestUuid']);
		} else {
			$this->assertArrayNotHasKey('signRequestUuid', $payload);
		}
	}

	public function testFluentInterface(): void {
		$exception = new \Exception('Test', 1);

		// Test that all methods return self for chaining
		$builder = ErrorPayloadBuilder::fromException($exception)
			->setMessage('New message')
			->setCode(222)
			->setFileId(111)
			->addFileError(1, new \Exception('Error 1'))
			->addFileError(2, new \Exception('Error 2'));

		$this->assertInstanceOf(ErrorPayloadBuilder::class, $builder);

		$payload = $builder->build();
		$this->assertEquals('New message', $payload['message']);
		$this->assertEquals(222, $payload['code']);
		$this->assertEquals(111, $payload['fileId']);
		$this->assertCount(2, $payload['fileErrors']);
	}

	public function testTimestampPresent(): void {
		$builder = new ErrorPayloadBuilder();
		$payload = $builder->setMessage('Test')->build();

		$this->assertArrayHasKey('timestamp', $payload);
		// Validate ISO 8601 format
		$this->assertMatchesRegularExpression(
			'/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}/',
			$payload['timestamp']
		);
	}
}
