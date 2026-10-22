<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Handler\SignEngine;

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Handler\SignEngine\IPkcs12SignEngineHandler;
use OCA\Libresign\Handler\SignEngine\ISignEngineHandler;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Handler\SignEngine\Pkcs7Handler;
use OCA\Libresign\Handler\SignEngine\SignEngineHandler;
use PHPUnit\Framework\Attributes\DataProvider;

final class IPkcs12SignEngineHandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	public function testExtendsCommonContract(): void {
		$this->assertTrue(
			is_subclass_of(IPkcs12SignEngineHandler::class, ISignEngineHandler::class),
			'IPkcs12SignEngineHandler must extend ISignEngineHandler.',
		);
	}

	public function testSignEngineHandlerImplementsSpecializedContract(): void {
		$this->assertTrue(
			is_subclass_of(SignEngineHandler::class, IPkcs12SignEngineHandler::class),
			'SignEngineHandler must implement IPkcs12SignEngineHandler.',
		);
	}

	#[DataProvider('providerHandler')]
	public function testHandlerSatisfiesSpecializedContract(string $handler): void {
		$this->assertTrue(
			is_subclass_of($handler, IPkcs12SignEngineHandler::class),
			$handler . ' must satisfy IPkcs12SignEngineHandler.',
		);
		$this->assertTrue(
			is_subclass_of($handler, ISignEngineHandler::class),
			$handler . ' must satisfy ISignEngineHandler.',
		);
	}

	public static function providerHandler(): array {
		return [
			[Pkcs12Handler::class],
			[Pkcs7Handler::class],
		];
	}

	#[DataProvider('providerCredentialMethod')]
	public function testCredentialMethodIsDeclaredOnlyBySpecializedContract(string $method): void {
		$specialized = new \ReflectionClass(IPkcs12SignEngineHandler::class);
		$this->assertTrue(
			$specialized->hasMethod($method),
			$method . ' must be declared by IPkcs12SignEngineHandler.',
		);
		$this->assertSame(
			IPkcs12SignEngineHandler::class,
			$specialized->getMethod($method)->getDeclaringClass()->getName(),
			$method . ' must be declared by IPkcs12SignEngineHandler itself.',
		);

		$common = new \ReflectionClass(ISignEngineHandler::class);
		$this->assertFalse(
			$common->hasMethod($method),
			$method . ' must not be declared by ISignEngineHandler.',
		);
	}

	public static function providerCredentialMethod(): array {
		return [
			['setCertificate'],
			['getCertificate'],
			['readCertificate'],
			['setPassword'],
			['getPassword'],
		];
	}

	public function testUnrelatedMethodsRemainOnCommonContract(): void {
		$common = new \ReflectionClass(ISignEngineHandler::class);
		foreach ([
			'setInputFile',
			'getInputFile',
			'sign',
			'getSignedContent',
			'getSignatureParams',
			'setSignatureParams',
			'getCertificateChain',
			'getLastSignedDate',
		] as $method) {
			$this->assertTrue(
				$common->hasMethod($method),
				$method . ' must remain declared by ISignEngineHandler.',
			);
		}
	}
}
