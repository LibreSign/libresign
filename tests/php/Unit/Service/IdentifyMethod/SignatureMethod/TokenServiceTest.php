<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\IdentifyMethod\SignatureMethod;

use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\TokenService;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\TwofactorGatewayService;
use OCP\App\IAppManager;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IL10N;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class TokenServiceTest extends TestCase {
	private ISecureRandom&MockObject $secureRandom;
	private IHasher&MockObject $hasher;
	private MailService&MockObject $mailService;
	private IL10N&MockObject $l10n;
	private ContainerInterface&MockObject $container;
	private IAppManager&MockObject $appManager;
	private LoggerInterface&MockObject $logger;

	#[\Override]
	public function setUp(): void {
		parent::setUp();

		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->hasher = $this->createMock(IHasher::class);
		$this->mailService = $this->createMock(MailService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->container = $this->createMock(ContainerInterface::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	public function testSendCodeByGatewayThrowsWhenGatewayIsIncomplete(): void {
		$this->appManager->method('isEnabledForAnyone')->with('twofactor_gateway')->willReturn(true);
		$this->container->method('get')
			->with('OCA\\TwoFactorGateway\\Provider\\Gateway\\Factory')
			->willReturn(new TokenServiceGatewayFactoryStub(new TokenServiceGatewayProviderStub(false)));
		$this->secureRandom->expects($this->never())
			->method('generate');
		$this->l10n->method('t')
			->willReturnCallback(static fn (string $text, mixed $parameters = []): string => is_array($parameters)
				? vsprintf($text, $parameters)
				: sprintf($text, $parameters));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Gateway sms not configured on Two-Factor Gateway.');

		$this->createService()->sendCodeByGateway('+5511999999999', 'sms');
	}

	public function testSendCodeByGatewayUsesGatewayServiceAndReturnsHashedCode(): void {
		$this->appManager->method('isEnabledForAnyone')->with('twofactor_gateway')->willReturn(true);
		$provider = new TokenServiceGatewayProviderStub(true);
		$this->container->method('get')
			->with('OCA\\TwoFactorGateway\\Provider\\Gateway\\Factory')
			->willReturn(new TokenServiceGatewayFactoryStub($provider));
		$this->secureRandom->expects($this->once())
			->method('generate')
			->with(TokenService::TOKEN_LENGTH, ISecureRandom::CHAR_DIGITS)
			->willReturn('123456');
		$this->l10n->expects($this->once())
			->method('t')
			->with('%s is your LibreSign verification code.', '123456')
			->willReturn('123456 is your LibreSign verification code.');
		$this->hasher->expects($this->once())
			->method('hash')
			->with('123456')
			->willReturn('hashed-code');

		self::assertSame('hashed-code', $this->createService()->sendCodeByGateway('+5511999999999', 'sms'));
		self::assertSame([
			['identifier' => '+5511999999999', 'message' => '123456 is your LibreSign verification code.'],
		], $provider->sentMessages);
	}

	private function createService(): TokenService {
		return new TokenService(
			$this->secureRandom,
			$this->hasher,
			$this->mailService,
			$this->l10n,
			new TwofactorGatewayService(
				$this->container,
				$this->appManager,
				$this->logger,
			),
		);
	}
}

final class TokenServiceGatewayFactoryStub {
	public function __construct(
		private object $gateway,
	) {
	}

	public function get(string $name): object {
		return $this->gateway;
	}
}

final class TokenServiceGatewayProviderStub {
	/** @var list<array{identifier: string, message: string}> */
	public array $sentMessages = [];

	public function __construct(
		private bool $complete,
	) {
	}

	public function isComplete(): bool {
		return $this->complete;
	}

	public function send(string $identifier, string $message): void {
		$this->sentMessages[] = [
			'identifier' => $identifier,
			'message' => $message,
		];
	}
}
