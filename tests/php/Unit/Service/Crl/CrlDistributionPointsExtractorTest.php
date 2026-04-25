<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Crl;

use OCA\Libresign\Service\Crl\CrlDistributionPointsExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CrlDistributionPointsExtractorTest extends TestCase {
	private CrlDistributionPointsExtractor $extractor;

	protected function setUp(): void {
		$this->extractor = new CrlDistributionPointsExtractor();
	}

	#[DataProvider('crlDistributionPointExtractionProvider')]
	public function testExtractFromExtensions(array $extensions, bool $expectedHasExtension, array $expectedUrls): void {
		$result = $this->extractor->extractFromExtensions($extensions);

		$this->assertSame($expectedHasExtension, $result['hasExtension']);
		$this->assertSame($expectedUrls, $result['urls']);
	}

	/**
	 * RFC 5280 4.2.1.13 defines cRLDistributionPoints as DistributionPointName
	 * with URI represented in GeneralNames. Tests cover common OpenSSL textual
	 * outputs for HTTP and LDAP URIs and multiple distribution points.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: bool, 2: list<string>}>
	 */
	public static function crlDistributionPointExtractionProvider(): array {
		return [
			'oid-extension-with-http-uri' => [
				[
					'2.5.29.31' => "Full Name:\nURI:https://example.org/crl/root.crl",
				],
				true,
				['https://example.org/crl/root.crl'],
			],
			'x509v3-label-with-http-uri' => [
				[
					'X509v3 CRL Distribution Points' => "Full Name:\n URI : https://example.org/crl/issuer.crl",
				],
				true,
				['https://example.org/crl/issuer.crl'],
			],
			'rfc-ldap-uri-with-dn-and-query' => [
				[
					'crlDistributionPoints' => "Full Name:\nURI:ldap://ldap.example.com/cn=Example%20CA,ou=PKI,dc=example,dc=com?certificateRevocationList;binary",
				],
				true,
				['ldap://ldap.example.com/cn=Example%20CA,ou=PKI,dc=example,dc=com?certificateRevocationList;binary'],
			],
			'multiple-distribution-points-in-single-extension' => [
				[
					'2.5.29.31' => "Full Name:\nURI:https://pki.example.org/root.crl\nFull Name:\nURI:ldap://ldap.example.org/cn=RootCA,dc=example,dc=org?certificateRevocationList;binary",
				],
				true,
				[
					'https://pki.example.org/root.crl',
					'ldap://ldap.example.org/cn=RootCA,dc=example,dc=org?certificateRevocationList;binary',
				],
			],
			'array-extension-value-and-duplicates' => [
				[
					'2.5.29.31' => [
						'Full Name:',
						'URI:https://example.org/crl/root.crl',
						'URI:https://example.org/crl/root.crl',
					],
				],
				true,
				['https://example.org/crl/root.crl'],
			],
			'known-extension-without-uri' => [
				[
					'2.5.29.31' => 'Distribution Point Name: relativeName=CN=DP1',
				],
				true,
				[],
			],
			'unknown-extension-name-should-not-match' => [
				[
					'Issuer CRL Distribution Points' => "Full Name:\nURI:https://example.org/crl/issuer.crl",
				],
				false,
				[],
			],
		];
	}
}
