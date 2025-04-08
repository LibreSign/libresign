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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CertificateEngineFactoryTest extends TestCase {

	protected function tearDown(): void {
		$ref = new \ReflectionClass(CertificateEngineFactory::class);
		$prop = $ref->getProperty('engine');
		$prop->setAccessible(true);
		$prop->setValue(null);
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
		$mockHandler = $this->createMock($handlerClass);
		\OC::$server->registerService($handlerClass, function () use ($mockHandler) {
			return $mockHandler;
		});

		$factory = new CertificateEngineFactory();
		$actual = $factory->getEngine($engineName, ['cert' => 'abc']);

		$this->assertSame($mockHandler, $actual);
	}

	#[DataProvider('providerThrowsExceptionOnInvalidEngine')]
	public function testThrowsExceptionOnInvalidEngine(string $invalidName): void {
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Certificate engine not found: ' . $invalidName);

		$factory = new CertificateEngineFactory();
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
