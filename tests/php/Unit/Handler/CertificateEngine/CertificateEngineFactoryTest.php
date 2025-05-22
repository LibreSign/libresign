<?php

declare(strict_types=1);
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

	public function tearDown(): void {
		$ref = new \ReflectionClass(CertificateEngineFactory::class);
		$prop = $ref->getProperty('engine');
		$prop->setAccessible(true);
		$prop->setValue(null);
	}

	public function setUp(): void {
		$this->appConfig = $this->getMockAppConfig();
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
}
