<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Handler\CertificateEngine;

/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\CfsslHandler;
use OCA\Libresign\Handler\CertificateEngine\NoneHandler;
use OCA\Libresign\Handler\CertificateEngine\OpenSslHandler;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class CertificateEngineFactoryTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IAppConfig $appConfig;
	private OpenSslHandler&MockObject $openSslHandler;
	private CfsslHandler&MockObject $cfsslHandler;
	private NoneHandler&MockObject $noneHandler;

	private function resetEngineCache(): void {
		$ref = new \ReflectionClass(CertificateEngineFactory::class);
		$prop = $ref->getProperty('engine');
		$prop->setValue(null, null);
	}

	#[\Override]
	public function tearDown(): void {
		$this->resetEngineCache();
	}

	#[\Override]
	public function setUp(): void {
		$this->resetEngineCache();
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->openSslHandler = $this->createMock(OpenSslHandler::class);
		$this->cfsslHandler = $this->createMock(CfsslHandler::class);
		$this->noneHandler = $this->createMock(NoneHandler::class);
	}

	private function getInstance(): CertificateEngineFactory {
		return new CertificateEngineFactory(
			$this->appConfig,
			$this->openSslHandler,
			$this->cfsslHandler,
			$this->noneHandler,
		);
	}

	public static function providerGetEngineReturnsCorrectHandler(): array {
		return [
			'openssl engine' => ['openssl', OpenSslHandler::class],
			'cfssl engine' => ['cfssl', CfsslHandler::class],
			'none engine' => ['none', NoneHandler::class],
		];
	}

	#[DataProvider('providerGetEngineReturnsCorrectHandler')]
	public function testGetEngineReturnsCorrectHandler(string $engineName, string $handlerClass): void {
		$mockHandler = match ($handlerClass) {
			OpenSslHandler::class => $this->openSslHandler,
			CfsslHandler::class => $this->cfsslHandler,
			NoneHandler::class => $this->noneHandler,
		};
		\OC::$server->registerService($handlerClass, fn () => $mockHandler);

		$factory = $this->getInstance();
		$actual = $factory->getEngine($engineName, ['cert' => 'abc']);

		$this->assertSame($mockHandler, $actual);
	}

	#[DataProvider('providerThrowsExceptionOnInvalidEngine')]
	public function testThrowsExceptionOnInvalidEngine(string $invalidName): void {
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Certificate engine not found: ' . $invalidName);

		$factory = $this->getInstance();
		$factory->getEngine($invalidName);
	}

	public static function providerThrowsExceptionOnInvalidEngine(): array {
		return [
			['invalid'],
			['test'],
			['fake'],
			['dummy'],
		];
	}

	public function testSetEngineUpdatesCachedHandlerWithoutRereadingAppConfig(): void {
		$this->noneHandler->expects($this->once())
			->method('setEngine')
			->with('none');
		$this->noneHandler->expects($this->once())
			->method('populateInstance')
			->with([])
			->willReturnSelf();

		$factory = $this->getInstance();
		$actual = $factory->setEngine('none');

		$this->assertSame($this->noneHandler, $actual);
		$this->assertSame($this->noneHandler, $factory->getEngine());
	}

	public function testGetEngineWithoutArgumentUsesConfiguredEngineAndCachesIt(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appConfig->expects($this->once())
			->method('getValueString')
			->with('libresign', 'certificate_engine', 'openssl')
			->willReturn('cfssl');
		$this->cfsslHandler->expects($this->exactly(2))
			->method('populateInstance')
			->with([])
			->willReturnSelf();

		$factory = $this->getInstance();

		$this->assertSame($this->cfsslHandler, $factory->getEngine());
		$this->assertSame($this->cfsslHandler, $factory->getEngine());
	}

	public function testGetEngineWithExplicitNameReusesMatchingCachedHandler(): void {
		$populateCalls = 0;
		$this->noneHandler->expects($this->once())
			->method('getName')
			->willReturn('none');
		$this->noneHandler->expects($this->exactly(2))
			->method('populateInstance')
			->willReturnCallback(function (array $rootCert) use (&$populateCalls) {
				++$populateCalls;
				if ($populateCalls === 1) {
					$this->assertSame([], $rootCert);
				} else {
					$this->assertSame(['cert' => 'abc'], $rootCert);
				}
				return $this->noneHandler;
			});

		$factory = $this->getInstance();

		$this->assertSame($this->noneHandler, $factory->getEngine('none'));
		$this->assertSame($this->noneHandler, $factory->getEngine('none', ['cert' => 'abc']));
	}

	public function testGetEngineWithDifferentExplicitNameReplacesCachedHandler(): void {
		$this->openSslHandler->expects($this->once())
			->method('getName')
			->willReturn('openssl');
		$this->openSslHandler->expects($this->once())
			->method('populateInstance')
			->with([])
			->willReturnSelf();
		$this->cfsslHandler->expects($this->once())
			->method('populateInstance')
			->with([])
			->willReturnSelf();

		$factory = $this->getInstance();

		$this->assertSame($this->openSslHandler, $factory->getEngine('openssl'));
		$this->assertSame($this->cfsslHandler, $factory->getEngine('cfssl'));
	}
}
