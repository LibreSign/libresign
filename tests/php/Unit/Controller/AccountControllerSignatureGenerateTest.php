<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\AccountController;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Service\AccountService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

final class AccountControllerSignatureGenerateTest extends TestCase {
	private AccountService&MockObject $accountService;
	private Pkcs12Handler&MockObject $pkcs12Handler;
	private AccountController $controller;

	protected function setUp(): void {
		$this->accountService = $this->createMock(AccountService::class);
		$this->pkcs12Handler = $this->createMock(Pkcs12Handler::class);
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('user@example.test');
		$user->method('getUID')->willReturn('user');
		$user->method('getDisplayName')->willReturn('User');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$reflection = new ReflectionClass(AccountController::class);
		$this->controller = $reflection->newInstanceWithoutConstructor();
		foreach ([
			'accountService' => $this->accountService,
			'pkcs12Handler' => $this->pkcs12Handler,
			'userSession' => $userSession,
			'logger' => $this->createMock(LoggerInterface::class),
			'request' => $this->createMock(IRequest::class),
		] as $property => $value) {
			$reflection->getProperty($property)->setValue($this->controller, $value);
		}
	}

	public static function failures(): array {
		return [
			'business failure' => [new LibresignException('Invalid password'), Http::STATUS_UNPROCESSABLE_ENTITY],
			'invalid argument' => [new \InvalidArgumentException('Invalid password'), Http::STATUS_UNPROCESSABLE_ENTITY],
			'unexpected failure' => [new \RuntimeException('openssl is unavailable'), Http::STATUS_INTERNAL_SERVER_ERROR],
		];
	}

	#[DataProvider('failures')]
	public function testSignatureGenerateMapsTheFailureToItsStatus(\Throwable $failure, int $status): void {
		$this->accountService->method('validateCertificateData')->willThrowException($failure);

		$response = $this->controller->signatureGenerate('secret');

		$this->assertSame($status, $response->getStatus());
		$this->assertSame(['message' => $failure->getMessage()], $response->getData());
	}
}
