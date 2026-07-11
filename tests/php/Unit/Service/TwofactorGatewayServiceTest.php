<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\TwofactorGatewayService;
use OCP\App\IAppManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

final class TwofactorGatewayServiceTest extends TestCase {
	private ContainerInterface&MockObject $container;
	private IAppManager&MockObject $appManager;
	private LoggerInterface&MockObject $logger;

	#[\Override]
	public function setUp(): void {
		parent::setUp();

		$this->container = $this->createMock(ContainerInterface::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	public function testEnsureAvailableThrowsWhenFactoryServiceIsMissing(): void {
		$this->appManager->method('isEnabledForAnyone')->with('twofactor_gateway')->willReturn(true);
		$this->container->method('get')
			->with('OCA\\TwoFactorGateway\\Provider\\Gateway\\Factory')
			->willThrowException(new class extends \Exception implements NotFoundExceptionInterface {
			});

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('App Two-Factor Gateway is not installed.');

		$this->createService()->ensureAvailable('sms');
	}

	public function testIsGatewayCompleteReturnsFalseWhenAppIsDisabled(): void {
		$this->appManager->method('isEnabledForAnyone')->with('twofactor_gateway')->willReturn(false);
		$this->container->expects($this->never())->method('get');

		self::assertFalse($this->createService()->isGatewayComplete('sms'));
	}

	#[DataProvider('providerGatewayCompleteness')]
	public function testIsGatewayCompleteReturnsGatewayStatus(bool $gatewayComplete): void {
		$this->appManager->method('isEnabledForAnyone')->with('twofactor_gateway')->willReturn(true);
		$this->logger->expects($this->never())->method('warning');
		$this->container->method('get')
			->with('OCA\\TwoFactorGateway\\Provider\\Gateway\\Factory')
			->willReturn(new TwofactorGatewayFactoryStub(new TwofactorGatewayProviderStub($gatewayComplete)));

		self::assertSame($gatewayComplete, $this->createService()->isGatewayComplete('sms'));
	}

	public static function providerGatewayCompleteness(): array {
		return [
			'complete' => [true],
			'incomplete' => [false],
		];
	}

	public function testIsGatewayCompleteReturnsFalseWhenProviderContractChanges(): void {
		$this->appManager->method('isEnabledForAnyone')->with('twofactor_gateway')->willReturn(true);
		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'Twofactor gateway provider does not expose isComplete().',
				$this->arrayHasKey('gateway')
			);
		$this->container->method('get')
			->with('OCA\\TwoFactorGateway\\Provider\\Gateway\\Factory')
			->willReturn(new TwofactorGatewayFactoryStub(new class {
				public function send(string $identifier, string $message): void {
				}
			}));

		self::assertFalse($this->createService()->isGatewayComplete('sms'));
	}

	public function testSendForwardsIdentifierAndMessage(): void {
		$this->appManager->method('isEnabledForAnyone')->with('twofactor_gateway')->willReturn(true);
		$provider = new TwofactorGatewayProviderStub(true);
		$this->container->method('get')
			->with('OCA\\TwoFactorGateway\\Provider\\Gateway\\Factory')
			->willReturn(new TwofactorGatewayFactoryStub($provider));

		$this->createService()->send('sms', '+5511999999999', 'hello');

		self::assertSame([
			['identifier' => '+5511999999999', 'message' => 'hello'],
		], $provider->sentMessages);
	}

	private function createService(): TwofactorGatewayService {
		return new TwofactorGatewayService(
			$this->container,
			$this->appManager,
			$this->logger,
		);
	}
}

final class TwofactorGatewayFactoryStub {
	public function __construct(
		private object $gateway,
	) {
	}

	public function get(string $name): object {
		return $this->gateway;
	}
}

final class TwofactorGatewayProviderStub {
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
