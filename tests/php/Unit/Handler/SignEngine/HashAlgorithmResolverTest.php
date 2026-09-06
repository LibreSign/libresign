<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Handler\SignEngine;

use OCA\Libresign\Handler\SignEngine\HashAlgorithmResolver;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignatureHashAlgorithm\SignatureHashAlgorithmPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HashAlgorithmResolverTest extends TestCase {
	private PolicyService&MockObject $policyService;

	#[\Override]
	protected function setUp(): void {
		$this->policyService = $this->createMock(PolicyService::class);
	}

	private function getInstance(mixed $configuredAlgorithm): HashAlgorithmResolver {
		$this->policyService
			->method('resolve')
			->with(SignatureHashAlgorithmPolicy::KEY)
			->willReturn(
				(new ResolvedPolicy())
					->setPolicyKey(SignatureHashAlgorithmPolicy::KEY)
					->setEffectiveValue($configuredAlgorithm)
			);

		return new HashAlgorithmResolver($this->policyService);
	}

	#[DataProvider('providerSignatureHashAlgorithm')]
	public function testForSignature(mixed $configuredAlgorithm, ?float $pdfVersion, string $expected): void {
		$resolver = $this->getInstance($configuredAlgorithm);

		$this->assertSame($expected, $resolver->forSignature($pdfVersion));
	}

	public static function providerSignatureHashAlgorithm(): array {
		return [
			// Unknown PDF version: only the configured algorithm decides.
			'unknown version keeps a supported algorithm' => ['SHA384', null, 'SHA384'],
			'unknown version keeps RIPEMD160' => ['RIPEMD160', null, 'RIPEMD160'],
			'unknown version falls back on an empty algorithm' => ['', null, 'SHA256'],
			'unknown version falls back on an unsupported algorithm' => ['XYZ', null, 'SHA256'],
			'unknown version falls back on an unset policy' => [null, null, 'SHA256'],
			// JSignPdf only accepts SHA1 in PDFs older than 1.6.
			'PDF 1.0 is signed with SHA1' => ['SHA256', 1.0, 'SHA1'],
			'PDF 1.5 is signed with SHA1' => ['SHA512', 1.5, 'SHA1'],
			// Between 1.6 and 1.7 JSignPdf only accepts SHA256.
			'PDF 1.6 is signed with SHA256' => ['SHA384', 1.6, 'SHA256'],
			'PDF 1.6 ignores an unsupported algorithm' => ['XYZ', 1.6, 'SHA256'],
			// From 1.7 on the configured algorithm is used, except SHA1.
			'PDF 1.7 keeps the configured SHA384' => ['SHA384', 1.7, 'SHA384'],
			'PDF 1.7 keeps the configured SHA512' => ['SHA512', 1.7, 'SHA512'],
			'PDF 1.7 keeps the configured RIPEMD160' => ['RIPEMD160', 1.7, 'RIPEMD160'],
			'PDF 1.7 replaces SHA1 with SHA256' => ['SHA1', 1.7, 'SHA256'],
			'PDF 2.0 replaces SHA1 with SHA256' => ['SHA1', 2.0, 'SHA256'],
			'PDF 2.0 falls back on an unsupported algorithm' => ['XYZ', 2.0, 'SHA256'],
			'PDF 2.0 keeps the configured SHA512' => ['SHA512', 2.0, 'SHA512'],
		];
	}

	#[DataProvider('providerPdfVersionUpgrade')]
	public function testRequiresPdfVersionUpgradeForSha256(mixed $configuredAlgorithm, float $pdfVersion, bool $expected): void {
		$resolver = $this->getInstance($configuredAlgorithm);

		$this->assertSame($expected, $resolver->requiresPdfVersionUpgradeForSha256($pdfVersion));
	}

	public static function providerPdfVersionUpgrade(): array {
		return [
			'SHA256 in a PDF 1.2 needs the upgrade' => ['SHA256', 1.2, true],
			'SHA256 in a PDF 1.5 needs the upgrade' => ['SHA256', 1.5, true],
			'SHA256 in a PDF 1.6 does not need the upgrade' => ['SHA256', 1.6, false],
			'SHA256 in a PDF 1.7 does not need the upgrade' => ['SHA256', 1.7, false],
			'SHA1 in a PDF 1.5 does not need the upgrade' => ['SHA1', 1.5, false],
			'SHA512 in a PDF 1.5 does not need the upgrade' => ['SHA512', 1.5, false],
			'an unset policy in a PDF 1.5 does not need the upgrade' => [null, 1.5, false],
		];
	}
}
