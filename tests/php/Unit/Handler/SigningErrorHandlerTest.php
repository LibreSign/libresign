<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Handler;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\SigningErrorHandler;
use OCA\Libresign\Helper\JSActions;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SigningErrorHandlerTest extends TestCase {
	private SigningErrorHandler $handler;
	private IL10N&MockObject $l10n;
	private IRequest&MockObject $request;
	private LoggerInterface&MockObject $logger;

	protected function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->request = $this->createMock(IRequest::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->l10n->method('t')->willReturnCallback(fn ($text) => $text);

		$this->handler = new SigningErrorHandler(
			$this->l10n,
			$this->request,
			$this->logger
		);
	}

	public static function libresignExceptionProvider(): array {
		return [
			'code 400 triggers password creation action' => [
				'code' => 400,
				'message' => 'Password required',
				'expectedAction' => JSActions::ACTION_CREATE_SIGNATURE_PASSWORD,
			],
			'code 401 triggers do nothing action' => [
				'code' => 401,
				'message' => 'Unauthorized',
				'expectedAction' => JSActions::ACTION_DO_NOTHING,
			],
			'code 403 triggers do nothing action' => [
				'code' => 403,
				'message' => 'Forbidden',
				'expectedAction' => JSActions::ACTION_DO_NOTHING,
			],
			'code 404 triggers do nothing action' => [
				'code' => 404,
				'message' => 'Not found',
				'expectedAction' => JSActions::ACTION_DO_NOTHING,
			],
			'code 500 triggers do nothing action' => [
				'code' => 500,
				'message' => 'Server error',
				'expectedAction' => JSActions::ACTION_DO_NOTHING,
			],
			'code 2750 (SIGN_ID_DOC) triggers do nothing action with id doc requirement' => [
				'code' => JSActions::ACTION_SIGN_ID_DOC,
				'message' => 'You need to have an approved identification document to sign.',
				'expectedAction' => JSActions::ACTION_DO_NOTHING,
			],
		];
	}

	#[DataProvider('libresignExceptionProvider')]
	public function testLibresignExceptionHandling(
		int $code,
		string $message,
		int $expectedAction,
	): void {
		$exception = new LibresignException($message, $code);

		$result = $this->handler->handleException($exception);

		$this->assertArrayHasKey('action', $result);
		$this->assertArrayHasKey('errors', $result);
		$this->assertSame($expectedAction, $result['action']);
		$this->assertIsArray($result['errors']);
		$this->assertCount(1, $result['errors']);
		$this->assertSame($message, $result['errors'][0]['message']);
		$this->assertArrayHasKey('code', $result['errors'][0]);
		$this->assertSame($code, $result['errors'][0]['code']);
	}

	public static function knownErrorMessagesProvider(): array {
		return [
			'certificate password invalid' => [
				'message' => 'Certificate Password Invalid.',
				'shouldTranslate' => true,
			],
			'certificate password empty' => [
				'message' => 'Certificate Password is Empty.',
				'shouldTranslate' => true,
			],
			'host violates access rules' => [
				'message' => 'Host violates local access rules.',
				'shouldTranslate' => true,
			],
		];
	}

	#[DataProvider('knownErrorMessagesProvider')]
	public function testKnownErrorMessages(string $message, bool $shouldTranslate): void {
		$exception = new \RuntimeException($message);

		$result = $this->handler->handleException($exception);

		$this->assertSame(JSActions::ACTION_DO_NOTHING, $result['action']);
		$this->assertIsArray($result['errors']);
		$this->assertCount(1, $result['errors']);
		$this->assertSame($message, $result['errors'][0]['message']);
		$this->assertArrayNotHasKey('title', $result['errors'][0]);
	}

	public function testUnknownErrorLogsAndFormatsWithTechnicalDetails(): void {
		$exception = new \RuntimeException('Database connection failed');

		$this->request->method('getRemoteAddress')->willReturn('192.168.1.100');
		$this->request->method('getId')->willReturn('abc123xyz');

		$this->logger->expects($this->once())
			->method('error')
			->with(
				'Database connection failed',
				$this->callback(fn ($context) => isset($context['exception']))
			);

		$result = $this->handler->handleException($exception);

		$this->assertSame(JSActions::ACTION_DO_NOTHING, $result['action']);
		$this->assertIsArray($result['errors']);
		$this->assertCount(1, $result['errors']);
		$this->assertArrayHasKey('message', $result['errors'][0]);
		$this->assertArrayHasKey('title', $result['errors'][0]);
		$this->assertSame('Internal Server Error', $result['errors'][0]['title']);
		$this->assertStringContainsString('192.168.1.100', $result['errors'][0]['message']);
		$this->assertStringContainsString('abc123xyz', $result['errors'][0]['message']);
		$this->assertStringContainsString('Database connection failed', $result['errors'][0]['message']);
	}

	public function testUnknownErrorDoesNotLogKnownErrors(): void {
		$exception = new \RuntimeException('Certificate Password Invalid.');

		$this->logger->expects($this->never())->method('error');

		$this->handler->handleException($exception);
	}

	public function testGenericExceptionStructure(): void {
		$exception = new \Exception('Test error');

		$this->request->method('getRemoteAddress')->willReturn('127.0.0.1');
		$this->request->method('getId')->willReturn('test-id');

		$result = $this->handler->handleException($exception);

		$this->assertArrayHasKey('action', $result);
		$this->assertArrayHasKey('errors', $result);
		$this->assertIsInt($result['action']);
		$this->assertIsArray($result['errors']);
		$this->assertGreaterThan(0, count($result['errors']));
	}
}
