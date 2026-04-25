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
			'rfc-structure-with-reasons-and-crl-issuer' => [
				[
					'2.5.29.31' => "Full Name:\n URI:http://crl.example.org/root.crl\nReasons: keyCompromise, cACompromise\nCRL Issuer:\n DirName:/C=BR/O=Example/CN=Example CRL Issuer",
				],
				true,
				['http://crl.example.org/root.crl'],
			],
			'extension-name-is-trimmed-and-case-insensitive' => [
				[
					'  X509V3 CRL Distribution Points  ' => "Full Name:\n URI:https://example.org/crl/mixed-case.crl",
				],
				true,
				['https://example.org/crl/mixed-case.crl'],
			],
			'uri-token-is-case-insensitive' => [
				[
					'2.5.29.31' => "Full Name:\nuri:ldap://ldap.example.net/cn=CA,dc=example,dc=net?certificateRevocationList;binary",
				],
				true,
				['ldap://ldap.example.net/cn=CA,dc=example,dc=net?certificateRevocationList;binary'],
			],
			'uri-with-tabs-and-extra-whitespace' => [
				[
					'2.5.29.31' => "Full Name:\n\tURI\t:\t https://example.org/crl/with-tabs.crl",
				],
				true,
				['https://example.org/crl/with-tabs.crl'],
			],
			'uri-line-with-closing-parenthesis-from-formatted-output' => [
				[
					'2.5.29.31' => "Distribution Point (1):\nURI:https://example.org/crl/formatted.crl)",
				],
				true,
				['https://example.org/crl/formatted.crl'],
			],
			'multiple-supported-extension-keys-are-merged-and-deduplicated' => [
				[
					'2.5.29.31' => "Full Name:\nURI:https://example.org/crl/shared.crl",
					'crlDistributionPoints' => "Full Name:\nURI:https://example.org/crl/shared.crl\nURI:https://example.org/crl/extra.crl",
				],
				true,
				[
					'https://example.org/crl/shared.crl',
					'https://example.org/crl/extra.crl',
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
			'known-extension-with-general-names-but-no-uri' => [
				[
					'X509v3 CRL Distribution Points' => "Full Name:\nDNS:crl.example.org\nDirName:/C=BR/O=Example/CN=CRL Directory",
				],
				true,
				[],
			],
			'multiple-supported-keys-preserve-first-seen-order' => [
				[
					'crlDistributionPoints' => "Full Name:\nURI:https://example.org/crl/first.crl",
					'2.5.29.31' => "Full Name:\nURI:https://example.org/crl/second.crl",
				],
				true,
				[
					'https://example.org/crl/first.crl',
					'https://example.org/crl/second.crl',
				],
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
