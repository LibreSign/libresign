<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing\External;

use OCA\Libresign\Service\Signing\External\ContextFactory;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureProfile;
use PHPUnit\Framework\TestCase;

final class ContextFactoryTest extends TestCase {
	public function testBuildsExpectedPadesContextWithoutInfrastructure(): void {
		$context = ContextFactory::create('%PDF-1.4', '  ', 'pades-baseline-b', 'synthetic-certificate');
		self::assertSame('%PDF-1.4', $context->pdf->content);
		self::assertSame('synthetic-certificate', $context->certificate->certificatePem);
		self::assertSame(SignatureProfile::PadesBaselineB, $context->options->signatureProfile);
		self::assertSame('Synthetic CSC signer', $context->options->metadata->actor->name);
		self::assertFalse($context->options->useDefaultAppearance);
	}

	public function testUnsupportedProfileCannotCreateContext(): void {
		$this->expectException(\DomainException::class);
		ContextFactory::create('%PDF-1.4', 'Signer', 'unknown', 'synthetic-certificate');
	}
}
