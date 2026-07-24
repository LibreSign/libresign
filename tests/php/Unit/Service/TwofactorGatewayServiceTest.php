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
			->with('OCA\\TwoFactorGateway\\Service\\GatewayDirectIntegrationService')
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
			->with('OCA\\TwoFactorGateway\\Service\\GatewayDirectIntegrationService')
			->willReturn(new TwofactorGatewayIntegrationStub($gatewayComplete));

		self::assertSame($gatewayComplete, $this->createService()->isGatewayComplete('sms'));
	}

	public static function providerGatewayCompleteness(): array {
		return [
			'complete' => [true],
			'incomplete' => [false],
		];
	}

	public function testIsGatewayCompleteReturnsFalseWhenIntegrationContractChanges(): void {
		$this->appManager->method('isEnabledForAnyone')->with('twofactor_gateway')->willReturn(true);
		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'Unable to determine twofactor gateway completeness.',
				$this->arrayHasKey('gateway')
			);
		$this->container->method('get')
			->with('OCA\\TwoFactorGateway\\Service\\GatewayDirectIntegrationService')
			->willReturn(new class {
				public function ensureAvailable(string $gatewayName): void {
				}

				public function send(string $gatewayName, string $identifier, string $message): void {
				}
			});

		self::assertFalse($this->createService()->isGatewayComplete('sms'));
	}

	public function testSendForwardsIdentifierAndMessage(): void {
		$this->appManager->method('isEnabledForAnyone')->with('twofactor_gateway')->willReturn(true);
		$integrationService = new TwofactorGatewayIntegrationStub(true);
		$this->container->method('get')
			->with('OCA\\TwoFactorGateway\\Service\\GatewayDirectIntegrationService')
			->willReturn($integrationService);

		$this->createService()->send('sms', '+5511999999999', 'hello');

		self::assertSame([
			['gateway' => 'sms', 'identifier' => '+5511999999999', 'message' => 'hello'],
		], $integrationService->sentMessages);
	}

	private function createService(): TwofactorGatewayService {
		return new TwofactorGatewayService(
			$this->container,
			$this->appManager,
			$this->logger,
		);
	}
}

final class TwofactorGatewayIntegrationStub {
	/** @var list<array{gateway: string, identifier: string, message: string}> */
	public array $sentMessages = [];

	public function __construct(
		private bool $complete,
	) {
	}

	public function ensureAvailable(string $gatewayName): void {
	}

	public function isGatewayComplete(string $gatewayName): bool {
		return $this->complete;
	}

	public function send(string $gatewayName, string $identifier, string $message): void {
		$this->sentMessages[] = [
			'gateway' => $gatewayName,
			'identifier' => $identifier,
			'message' => $message,
		];
	}
}
