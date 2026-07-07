<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signature;

use OCA\Libresign\Service\Signature\PdfSignatureValidationService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCA\Libresign\Vendor\LibreSign\PdfSignatureValidator\Exception\UnsignedPdfException;
use OCA\Libresign\Vendor\LibreSign\PdfSignatureValidator\Model\ValidationResult;
use OCA\Libresign\Vendor\LibreSign\PdfSignatureValidator\Model\ValidationState;
use OCP\IAppConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class PdfSignatureValidationServiceTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private IL10N&MockObject $l10n;
	private LoggerInterface&MockObject $logger;

	public function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appConfig->method('getValueString')->willReturn('');
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->willReturnCallback(static fn (string $text): string => $text);
		$this->logger = $this->createMock(LoggerInterface::class);
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

	public function testMapReasonUsesDictionaryForKnownReason(): void {
		$service = $this->newServiceWithoutConstructor();
		$result = $this->invokePrivateMethod(
			$service,
			'mapSignatureValidation',
			new ValidationResult(ValidationState::DIGEST_MISMATCH, 'PDF content hash does not match signed digest')
		);

		$this->assertSame('PDF content hash does not match signed digest', $result['reason']);
	}

	public function testMapReasonKeepsUnknownReasonUntouched(): void {
		$service = $this->newServiceWithoutConstructor();
		$result = $this->invokePrivateMethod(
			$service,
			'mapSignatureValidation',
			new ValidationResult(ValidationState::DIGEST_MISMATCH, 'custom runtime detail')
		);

		$this->assertSame('custom runtime detail', $result['reason']);
	}

	public function testValidateFromStringReturnsEmptyListForUnsignedPdfException(): void {
		$this->logger->expects($this->never())->method('warning');

		$service = new class($this->appConfig, $this->l10n, $this->logger) extends PdfSignatureValidationService {
			protected function validateNativeFromString(string $pdfContent): array {
				throw new UnsignedPdfException('Unsigned file.');
			}
		};

		$this->assertSame([], $service->validateFromString('%PDF-1.7'));
	}

	public function testValidateFromStringPropagatesUnexpectedRuntimeException(): void {
		$this->logger->expects($this->never())->method('warning');

		$service = new class($this->appConfig, $this->l10n, $this->logger) extends PdfSignatureValidationService {
			protected function validateNativeFromString(string $pdfContent): array {
				throw new \RuntimeException('validator boom');
			}
		};

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('validator boom');
		$service->validateFromString('%PDF-1.7');
	}

	private function newServiceWithoutConstructor(): PdfSignatureValidationService {
		$reflection = new \ReflectionClass(PdfSignatureValidationService::class);
		/** @var PdfSignatureValidationService $service */
		$service = $reflection->newInstanceWithoutConstructor();

		$l10nProperty = $reflection->getProperty('l10n');
		$l10nProperty->setValue($service, $this->l10n);

		return $service;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function invokePrivateMethod(PdfSignatureValidationService $service, string $method, ValidationResult $result): array {
		$reflection = new \ReflectionClass($service);
		$target = $reflection->getMethod($method);
		/** @var array<string, mixed> $mapped */
		$mapped = $target->invoke($service, $result);
		return $mapped;
	}
}
