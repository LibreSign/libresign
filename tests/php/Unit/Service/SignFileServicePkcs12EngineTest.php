<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\SignEngine\IPkcs12SignEngineHandler;
use OCA\Libresign\Handler\SignEngine\ISignEngineHandler;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Handler\SignEngine\Pkcs7Handler;
use OCA\Libresign\Service\SignFileService;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers the PKCS#12 credential boundary of SignFileService.
 *
 * The signing workflow receives the PKCS#12/PFX credential inside LibreSign,
 * so the resolved engine must implement IPkcs12SignEngineHandler. The common
 * ISignEngineHandler contract does not require it.
 */
final class SignFileServicePkcs12EngineTest extends \OCA\Libresign\Tests\Unit\TestCase {
	public static function providerSupportedHandler(): array {
		return [
			'PDF handler' => [Pkcs12Handler::class],
			'detached PKCS#7 handler' => [Pkcs7Handler::class],
		];
	}

	#[DataProvider('providerSupportedHandler')]
	public function testGetPkcs12EngineReturnsTheResolvedEngine(string $handlerClass): void {
		$engine = $this->createMock($handlerClass);
		$service = $this->getService($engine);

		$result = self::invokePrivate($service, 'getPkcs12Engine');

		$this->assertSame($engine, $result);
	}

	public function testGetPkcs12EngineRejectsAnEngineWithoutThePkcs12Contract(): void {
		$engine = $this->createMock(ISignEngineHandler::class);
		$service = $this->getService($engine);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('The sign engine does not support PKCS#12 credentials.');

		self::invokePrivate($service, 'getPkcs12Engine');
	}

	public function testReadCertificateUsesThePkcs12CredentialContract(): void {
		$certificateInfo = ['serialNumber' => 'ABC123'];
		$engine = $this->createMock(IPkcs12SignEngineHandler::class);
		$engine->expects($this->once())
			->method('readCertificate')
			->willReturn($certificateInfo);
		$service = $this->getService($engine);

		$result = self::invokePrivate($service, 'readCertificate');

		$this->assertSame($certificateInfo, $result);
	}

	public function testReadCertificateRejectsAnEngineWithoutThePkcs12Contract(): void {
		$engine = $this->createMock(ISignEngineHandler::class);
		$service = $this->getService($engine);

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('The sign engine does not support PKCS#12 credentials.');

		self::invokePrivate($service, 'readCertificate');
	}

	private function getService(ISignEngineHandler $engine): SignFileService {
		$service = $this->getMockBuilder(SignFileService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getEngine'])
			->getMock();
		$service->method('getEngine')
			->willReturn($engine);

		return $service;
	}
}
