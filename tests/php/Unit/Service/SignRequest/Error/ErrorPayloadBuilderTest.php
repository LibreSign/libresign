<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignRequest\Error;

use OCA\Libresign\Service\SignRequest\Error\ErrorPayloadBuilder;
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

	public function testBuildWithFile(): void {
		$builder = new ErrorPayloadBuilder();
		$payload = $builder
			->setMessage('File error')
			->setCode(400)
			->setFileId(123)
			->build();

		$this->assertEquals(123, $payload['fileId']);
	}

	public function testBuildWithSignRequest(): void {
		$builder = new ErrorPayloadBuilder();
		$payload = $builder
			->setMessage('Sign request error')
			->setCode(400)
			->setSignRequestId(456)
			->setSignRequestUuid('test-uuid-123')
			->build();

		$this->assertEquals(456, $payload['signRequestId']);
		$this->assertEquals('test-uuid-123', $payload['signRequestUuid']);
	}

	public function testBuildWithFileAndSignRequest(): void {
		$builder = new ErrorPayloadBuilder();
		$payload = $builder
			->setMessage('Combined error')
			->setCode(400)
			->setFileId(789)
			->setSignRequestId(456)
			->setSignRequestUuid('test-uuid-456')
			->build();

		$this->assertEquals(789, $payload['fileId']);
		$this->assertEquals(456, $payload['signRequestId']);
		$this->assertEquals('test-uuid-456', $payload['signRequestUuid']);
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

	public function testFromException(): void {
		$exception = new \Exception('Exception message', 789);

		$payload = ErrorPayloadBuilder::fromException($exception, 555, 666, 'exception-uuid')->build();

		$this->assertEquals('Exception message', $payload['message']);
		$this->assertEquals(789, $payload['code']);
		$this->assertEquals(555, $payload['fileId']);
		$this->assertEquals(666, $payload['signRequestId']);
		$this->assertEquals('exception-uuid', $payload['signRequestUuid']);
	}

	public function testFromExceptionWithoutContext(): void {
		$exception = new \Exception('Standalone error', 999);

		$payload = ErrorPayloadBuilder::fromException($exception)->build();

		$this->assertEquals('Standalone error', $payload['message']);
		$this->assertEquals(999, $payload['code']);
		$this->assertArrayNotHasKey('fileId', $payload);
		$this->assertArrayNotHasKey('signRequestId', $payload);
		$this->assertArrayNotHasKey('signRequestUuid', $payload);
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

	public function testNullFileAndSignRequestNotIncluded(): void {
		$builder = new ErrorPayloadBuilder();
		$payload = $builder
			->setMessage('Error without context')
			->setCode(400)
			->setFileId(null)
			->setSignRequestId(null)
			->setSignRequestUuid(null)
			->build();

		$this->assertArrayNotHasKey('fileId', $payload);
		$this->assertArrayNotHasKey('signRequestId', $payload);
		$this->assertArrayNotHasKey('signRequestUuid', $payload);
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
