<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Crl;

use OCA\Libresign\Service\Crl\CrlDistributionPointsExtractor;
use PHPUnit\Framework\TestCase;

final class CrlDistributionPointsExtractorTest extends TestCase {
	private CrlDistributionPointsExtractor $extractor;

	protected function setUp(): void {
		$this->extractor = new CrlDistributionPointsExtractor();
	}

	public function testExtractFromOidExtensionName(): void {
		$result = $this->extractor->extractFromExtensions([
			'2.5.29.31' => "Full Name:\nURI:https://example.org/crl/root.crl",
		]);

		$this->assertTrue($result['hasExtension']);
		$this->assertSame(['https://example.org/crl/root.crl'], $result['urls']);
	}

	public function testExtractFromX509LabelExtensionName(): void {
		$result = $this->extractor->extractFromExtensions([
			'X509v3 CRL Distribution Points' => "Full Name:\n URI : https://example.org/crl/issuer.crl",
		]);

		$this->assertTrue($result['hasExtension']);
		$this->assertSame(['https://example.org/crl/issuer.crl'], $result['urls']);
	}

	public function testIgnoreUnknownExtensionNameWithSimilarText(): void {
		$result = $this->extractor->extractFromExtensions([
			'Issuer CRL Distribution Points' => "Full Name:\nURI:https://example.org/crl/issuer.crl",
		]);

		$this->assertFalse($result['hasExtension']);
		$this->assertSame([], $result['urls']);
	}
}
