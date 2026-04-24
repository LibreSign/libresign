<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signature;

use LibreSign\PdfSignatureValidator\Model\ValidationResult;
use LibreSign\PdfSignatureValidator\Model\ValidationState;
use OCA\Libresign\Service\Signature\PdfSignatureValidationService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;

final class PdfSignatureValidationServiceTest extends TestCase {
	private IL10N&MockObject $l10n;

	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->willReturnCallback(static fn (string $text): string => $text);
	}

	public function testMapSignatureValidationWithEnumState(): void {
		$service = $this->newServiceWithoutConstructor();
		$result = $this->invokePrivateMethod(
			$service,
			'mapSignatureValidation',
			new ValidationResult(ValidationState::DIGEST_MISMATCH, 'hash mismatch')
		);

		$this->assertSame(3, $result['id']);
		$this->assertSame('Digest mismatch.', $result['label']);
		$this->assertSame('hash mismatch', $result['reason']);
		$this->assertFalse($result['isValid']);
	}

	public function testMapCertificateValidationWithEnumState(): void {
		$service = $this->newServiceWithoutConstructor();
		$result = $this->invokePrivateMethod(
			$service,
			'mapCertificateValidation',
			new ValidationResult(ValidationState::CERT_TRUSTED)
		);

		$this->assertSame(1, $result['id']);
		$this->assertSame('Certificate is trusted.', $result['label']);
		$this->assertTrue($result['isValid']);
	}

	private function newServiceWithoutConstructor(): PdfSignatureValidationService {
		$reflection = new \ReflectionClass(PdfSignatureValidationService::class);
		/** @var PdfSignatureValidationService $service */
		$service = $reflection->newInstanceWithoutConstructor();

		$l10nProperty = $reflection->getProperty('l10n');
		$l10nProperty->setAccessible(true);
		$l10nProperty->setValue($service, $this->l10n);

		return $service;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function invokePrivateMethod(PdfSignatureValidationService $service, string $method, ValidationResult $result): array {
		$reflection = new \ReflectionClass($service);
		$target = $reflection->getMethod($method);
		$target->setAccessible(true);
		/** @var array<string, mixed> $mapped */
		$mapped = $target->invoke($service, $result);
		return $mapped;
	}
}
