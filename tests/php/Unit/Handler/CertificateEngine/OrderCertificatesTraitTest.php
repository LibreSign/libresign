<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit;

use OCA\Libresign\Handler\CertificateEngine\OrderCertificatesTrait;

class Test {
	use OrderCertificatesTrait;
}

final class OrderCertificatesTraitTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private Test $orderCertificates;

	public function setUp(): void {
		$this->orderCertificates = new Test();
	}

	public function testEmptyCertList(): void {
		$this->expectExceptionMessage('Certificate list cannot be empty');
		$this->orderCertificates->orderCertificates([]);
	}

	/**
	 * @dataProvider dataInvalidStructure
	 */
	public function testInvalidStructure(array $certList): void {
		$this->expectExceptionMessage('Invalid certificate structure. Certificate must have "subject", "issuer", and "name".');
		$this->orderCertificates->orderCertificates($certList);
	}

	public static function dataInvalidStructure(): array {
		return [
			[[['fake']]],
			[[['name' => '']]],
			[[['name' => '', 'issuer' => '']]],
			[[['name' => '', 'subject' => '']]],
			[[['issuer' => '', 'subject' => '']]],
		];
	}

	/**
	 * @dataProvider dataIncompleteCertificateChain
	 */
	public function testIncompleteCertificateChain($certList, $expectedOrder): void {
		$result = $this->orderCertificates->orderCertificates($certList);
		$this->assertEquals($expectedOrder, $result);
	}

	public static function dataIncompleteCertificateChain(): array {
		return [
			'incomplete chain - leaf with invalid issuer' => [
				[
					[
						'name' => '/CN=Leaf',
						'subject' => ['CN' => 'Leaf'],
						'issuer' => ['CN' => 'Invalid'],
					],
					[
						'name' => '/CN=Root',
						'subject' => ['CN' => 'Root'],
						'issuer' => ['CN' => 'Root'],
					],
				],
				[
					[
						'name' => '/CN=Leaf',
						'subject' => ['CN' => 'Leaf'],
						'issuer' => ['CN' => 'Invalid'],
					],
					[
						'name' => '/CN=Root',
						'subject' => ['CN' => 'Root'],
						'issuer' => ['CN' => 'Root'],
					],
				],
			],
			'incomplete chain - intermediate with invalid issuer' => [
				[
					[
						'name' => '/CN=Leaf',
						'subject' => ['CN' => 'Leaf'],
						'issuer' => ['CN' => 'Intermediate'],
					],
					[
						'name' => '/CN=Intermediate',
						'subject' => ['CN' => 'Intermediate'],
						'issuer' => ['CN' => 'Invalid'],
					],
					[
						'name' => '/CN=Root',
						'subject' => ['CN' => 'Root'],
						'issuer' => ['CN' => 'Root'],
					],
				],
				[
					[
						'name' => '/CN=Leaf',
						'subject' => ['CN' => 'Leaf'],
						'issuer' => ['CN' => 'Intermediate'],
					],
					[
						'name' => '/CN=Intermediate',
						'subject' => ['CN' => 'Intermediate'],
						'issuer' => ['CN' => 'Invalid'],
					],
					[
						'name' => '/CN=Root',
						'subject' => ['CN' => 'Root'],
						'issuer' => ['CN' => 'Root'],
					],
				],
			],
		];
	}

	/**
	 * @dataProvider dataOrderCertificates
	 */
	public function testOrderCertificates(array $unordered, array $expected): void {
		$actual = $this->orderCertificates->orderCertificates($unordered);
		$this->assertEquals($expected, $actual);
	}

	public static function dataOrderCertificates(): array {
		return [
			'only one cert, with root' => [
				[
					[
						'name' => '/CN=Root',
						'subject' => ['CN' => 'Root'],
						'issuer' => ['CN' => 'Root'],
					],
				],
				[
					[
						'name' => '/CN=Root',
						'subject' => ['CN' => 'Root'],
						'issuer' => ['CN' => 'Root'],
					],
				],
			],
			'only one cert, without root' => [
				[
					[
						'name' => '/CN=Leaf',
						'subject' => ['CN' => 'Leaf'],
						'issuer' => ['CN' => 'Root'],
					],
				],
				[
					[
						'name' => '/CN=Leaf',
						'subject' => ['CN' => 'Leaf'],
						'issuer' => ['CN' => 'Root'],
					],
				],
			],
			'two certs, unordered' => [
				[
					[
						'name' => '/CN=Root',
						'subject' => ['CN' => 'Root'],
						'issuer' => ['CN' => 'Root'],
					],
					[
						'name' => '/CN=Leaf',
						'subject' => ['CN' => 'Leaf'],
						'issuer' => ['CN' => 'Root'],
					],
				],
				[
					[
						'name' => '/CN=Leaf',
						'subject' => ['CN' => 'Leaf'],
						'issuer' => ['CN' => 'Root'],
					],
					[
						'name' => '/CN=Root',
						'subject' => ['CN' => 'Root'],
						'issuer' => ['CN' => 'Root'],
					],
				],
			],
			'two certs, orderd' => [
				[
					[
						'name' => '/CN=Leaf',
						'subject' => ['CN' => 'Leaf'],
						'issuer' => ['CN' => 'Root'],
					],
					[
						'name' => '/CN=Root',
						'subject' => ['CN' => 'Root'],
						'issuer' => ['CN' => 'Root'],
					],
				],
				[
					[
						'name' => '/CN=Leaf',
						'subject' => ['CN' => 'Leaf'],
						'issuer' => ['CN' => 'Root'],
					],
					[
						'name' => '/CN=Root',
						'subject' => ['CN' => 'Root'],
						'issuer' => ['CN' => 'Root'],
					],
				],
			],
			'tree certs, unordered' => [
				[
					[
						'name' => '/CN=Intermediate',
						'subject' => ['CN' => 'Intermediate'],
						'issuer' => ['CN' => 'Root'],
					],
					[
						'name' => '/CN=Root',
						'subject' => ['CN' => 'Root'],
						'issuer' => ['CN' => 'Root'],
					],
					[
						'name' => '/CN=Leaf',
						'subject' => ['CN' => 'Leaf'],
						'issuer' => ['CN' => 'Intermediate'],
					],
				],
				[
					[
						'name' => '/CN=Leaf',
						'subject' => ['CN' => 'Leaf'],
						'issuer' => ['CN' => 'Intermediate'],
					],
					[
						'name' => '/CN=Intermediate',
						'subject' => ['CN' => 'Intermediate'],
						'issuer' => ['CN' => 'Root'],
					],
					[
						'name' => '/CN=Root',
						'subject' => ['CN' => 'Root'],
						'issuer' => ['CN' => 'Root'],
					],
				],
			],
			'Four certs, unordered' => [
				[
					[
						'name' => '/CN=Intermediate 2',
						'subject' => ['CN' => 'Intermediate 2'],
						'issuer' => ['CN' => 'Root'],
					],
					[
						'name' => '/CN=Intermediate 1',
						'subject' => ['CN' => 'Intermediate 1'],
						'issuer' => ['CN' => 'Intermediate 2'],
					],
					[
						'name' => '/CN=Root',
						'subject' => ['CN' => 'Root'],
						'issuer' => ['CN' => 'Root'],
					],
					[
						'name' => '/CN=Leaf',
						'subject' => ['CN' => 'Leaf'],
						'issuer' => ['CN' => 'Intermediate 1'],
					],
				],
				[
					[
						'name' => '/CN=Leaf',
						'subject' => ['CN' => 'Leaf'],
						'issuer' => ['CN' => 'Intermediate 1'],
					],
					[
						'name' => '/CN=Intermediate 1',
						'subject' => ['CN' => 'Intermediate 1'],
						'issuer' => ['CN' => 'Intermediate 2'],
					],
					[
						'name' => '/CN=Intermediate 2',
						'subject' => ['CN' => 'Intermediate 2'],
						'issuer' => ['CN' => 'Root'],
					],
					[
						'name' => '/CN=Root',
						'subject' => ['CN' => 'Root'],
						'issuer' => ['CN' => 'Root'],
					],
				],
			],
			'e-commerce certificate chain' => [
				[
					[
						'name' => '/C=US/O=TrustCorp/OU=Certificate Authority Division/CN=TrustCorp Global Root CA v3',
						'subject' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'Certificate Authority Division', 'CN' => 'TrustCorp Global Root CA v3'],
						'issuer' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'Certificate Authority Division', 'CN' => 'TrustCorp Global Root CA v3'],
					],
					[
						'name' => '/C=US/O=TrustCorp/OU=TrustCorp Global Root CA v3/CN=TrustCorp Business Intermediate CA v2',
						'subject' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'TrustCorp Global Root CA v3', 'CN' => 'TrustCorp Business Intermediate CA v2'],
						'issuer' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'Certificate Authority Division', 'CN' => 'TrustCorp Global Root CA v3'],
					],
					[
						'name' => '/C=US/O=TrustCorp/OU=Business Certificate Division/CN=TrustCorp E-Commerce CA v1',
						'subject' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'Business Certificate Division', 'CN' => 'TrustCorp E-Commerce CA v1'],
						'issuer' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'TrustCorp Global Root CA v3', 'CN' => 'TrustCorp Business Intermediate CA v2'],
					],
				],
				[
					[
						'name' => '/C=US/O=TrustCorp/OU=Business Certificate Division/CN=TrustCorp E-Commerce CA v1',
						'subject' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'Business Certificate Division', 'CN' => 'TrustCorp E-Commerce CA v1'],
						'issuer' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'TrustCorp Global Root CA v3', 'CN' => 'TrustCorp Business Intermediate CA v2'],
					],
					[
						'name' => '/C=US/O=TrustCorp/OU=TrustCorp Global Root CA v3/CN=TrustCorp Business Intermediate CA v2',
						'subject' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'TrustCorp Global Root CA v3', 'CN' => 'TrustCorp Business Intermediate CA v2'],
						'issuer' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'Certificate Authority Division', 'CN' => 'TrustCorp Global Root CA v3'],
					],
					[
						'name' => '/C=US/O=TrustCorp/OU=Certificate Authority Division/CN=TrustCorp Global Root CA v3',
						'subject' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'Certificate Authority Division', 'CN' => 'TrustCorp Global Root CA v3'],
						'issuer' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'Certificate Authority Division', 'CN' => 'TrustCorp Global Root CA v3'],
					],
				],
			],
		];
	}

	public function testBankingCertificateChainExample(): void {
		$bankingCerts = [
			[
				'name' => '/C=US/O=TrustCorp/OU=Certificate Authority Division/CN=TrustCorp Global Root CA v3',
				'subject' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'Certificate Authority Division', 'CN' => 'TrustCorp Global Root CA v3'],
				'issuer' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'Certificate Authority Division', 'CN' => 'TrustCorp Global Root CA v3'],
				'hash' => 'a2502f15',
			],
			[
				'name' => '/C=US/O=TrustCorp/OU=TrustCorp Global Root CA v3/CN=TrustCorp Business Intermediate CA v2',
				'subject' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'TrustCorp Global Root CA v3', 'CN' => 'TrustCorp Business Intermediate CA v2'],
				'issuer' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'Certificate Authority Division', 'CN' => 'TrustCorp Global Root CA v3'],
				'hash' => 'e674579a',
			],
			[
				'name' => '/C=US/O=TrustCorp/OU=Business Certificate Division/CN=TrustCorp E-Commerce CA v1',
				'subject' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'Business Certificate Division', 'CN' => 'TrustCorp E-Commerce CA v1'],
				'issuer' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'TrustCorp Global Root CA v3', 'CN' => 'TrustCorp Business Intermediate CA v2'],
				'hash' => 'bacf3335',
			],
		];

		$result = $this->orderCertificates->orderCertificates($bankingCerts);

		$this->assertCount(3, $result);
		$this->assertEquals('TrustCorp E-Commerce CA v1', $result[0]['subject']['CN']);
		$this->assertEquals('TrustCorp Business Intermediate CA v2', $result[1]['subject']['CN']);
		$this->assertEquals('TrustCorp Global Root CA v3', $result[2]['subject']['CN']);
	}

	public function testComplexCompanyCertificateChain(): void {
		$companyChain = [
			[
				'name' => '/C=US/O=TrustCorp/OU=Certificate Authority Division/CN=TrustCorp Global Root CA v3',
				'subject' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'Certificate Authority Division', 'CN' => 'TrustCorp Global Root CA v3'],
				'issuer' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'Certificate Authority Division', 'CN' => 'TrustCorp Global Root CA v3'],
			],
			[
				'name' => '/C=US/O=TrustCorp/OU=TrustCorp Global Root CA v3/CN=TrustCorp Business Intermediate CA v2',
				'subject' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'TrustCorp Global Root CA v3', 'CN' => 'TrustCorp Business Intermediate CA v2'],
				'issuer' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'Certificate Authority Division', 'CN' => 'TrustCorp Global Root CA v3'],
			],
			[
				'name' => '/C=US/O=TrustCorp/OU=Business Certificate Division/CN=TrustCorp E-Commerce CA v1',
				'subject' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'Business Certificate Division', 'CN' => 'TrustCorp E-Commerce CA v1'],
				'issuer' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'TrustCorp Global Root CA v3', 'CN' => 'TrustCorp Business Intermediate CA v2'],
			],
			[
				'name' => '/C=US/O=SecureSign Corp/ST=CA/L=San Francisco/OU=Digital Services/OU=87654321000198/OU=Business Certificate Division/OU=SSL Certificate A1/CN=SecureSign Digital Solutions Inc:87654321000198',
				'subject' => ['C' => 'US', 'O' => 'SecureSign Corp', 'ST' => 'CA', 'L' => 'San Francisco', 'OU' => 'Digital Services', 'CN' => 'SecureSign Digital Solutions Inc:87654321000198'],
				'issuer' => ['C' => 'US', 'O' => 'TrustCorp', 'OU' => 'Business Certificate Division', 'CN' => 'TrustCorp E-Commerce CA v1'],
			],
		];

		$result = $this->orderCertificates->orderCertificates($companyChain);

		$this->assertCount(4, $result);
		$this->assertEquals('SecureSign Digital Solutions Inc:87654321000198', $result[0]['subject']['CN']);
		$this->assertEquals('TrustCorp E-Commerce CA v1', $result[1]['subject']['CN']);
		$this->assertEquals('TrustCorp Business Intermediate CA v2', $result[2]['subject']['CN']);
		$this->assertEquals('TrustCorp Global Root CA v3', $result[3]['subject']['CN']);
	}

	/**
	 * @dataProvider dataValidateCertificateChain
	 */
	public function testValidateCertificateChain(array $certificates, array $expected): void {
		$result = $this->orderCertificates->validateCertificateChain($certificates);
		$this->assertEquals($expected, $result);
	}

	public static function dataValidateCertificateChain(): array {
		return [
			'valid complete chain' => [
				[
					[
						'name' => '/CN=Leaf',
						'subject' => ['CN' => 'Leaf'],
						'issuer' => ['CN' => 'Root'],
					],
					[
						'name' => '/CN=Root',
						'subject' => ['CN' => 'Root'],
						'issuer' => ['CN' => 'Root'],
					],
				],
				[
					'valid' => true,
					'hasRoot' => true,
					'isComplete' => true,
					'length' => 2,
				],
			],
			'valid chain with intermediate' => [
				[
					[
						'name' => '/CN=Leaf',
						'subject' => ['CN' => 'Leaf'],
						'issuer' => ['CN' => 'Intermediate'],
					],
					[
						'name' => '/CN=Intermediate',
						'subject' => ['CN' => 'Intermediate'],
						'issuer' => ['CN' => 'Root'],
					],
					[
						'name' => '/CN=Root',
						'subject' => ['CN' => 'Root'],
						'issuer' => ['CN' => 'Root'],
					],
				],
				[
					'valid' => true,
					'hasRoot' => true,
					'isComplete' => true,
					'length' => 3,
				],
			],
			'incomplete chain without root' => [
				[
					[
						'name' => '/CN=Leaf',
						'subject' => ['CN' => 'Leaf'],
						'issuer' => ['CN' => 'Missing'],
					],
				],
				[
					'valid' => true,
					'hasRoot' => false,
					'isComplete' => false,
					'length' => 1,
				],
			],
			'invalid structure - missing subject' => [
				[
					[
						'name' => '/CN=Invalid',
						'issuer' => ['CN' => 'Root'],
					],
				],
				[
					'valid' => false,
					'hasRoot' => false,
					'isComplete' => false,
					'length' => 1,
				],
			],
			'invalid structure - missing CN in subject' => [
				[
					[
						'name' => '/O=Test',
						'subject' => ['O' => 'Test'],
						'issuer' => ['CN' => 'Root'],
					],
				],
				[
					'valid' => false,
					'hasRoot' => false,
					'isComplete' => false,
					'length' => 1,
				],
			],
			'empty certificate list' => [
				[],
				[
					'valid' => false,
					'hasRoot' => false,
					'isComplete' => false,
					'length' => 0,
				],
			],
			'TrustCorp PKI chain validation' => [
				[
					[
						'name' => '/C=US/ST=California/L=San Francisco/O=TrustCorp/CN=TrustCorp Global Root CA v3',
						'subject' => ['C' => 'US', 'ST' => 'California', 'L' => 'San Francisco', 'O' => 'TrustCorp', 'CN' => 'TrustCorp Global Root CA v3'],
						'issuer' => ['C' => 'US', 'ST' => 'California', 'L' => 'San Francisco', 'O' => 'TrustCorp', 'CN' => 'TrustCorp Global Root CA v3'],
					],
					[
						'name' => '/C=US/ST=California/L=San Francisco/O=TrustCorp/OU=TrustCorp Global Root CA v3/CN=TrustCorp Government Intermediate CA v2',
						'subject' => ['C' => 'US', 'ST' => 'California', 'L' => 'San Francisco', 'O' => 'TrustCorp', 'OU' => 'TrustCorp Global Root CA v3', 'CN' => 'TrustCorp Government Intermediate CA v2'],
						'issuer' => ['C' => 'US', 'ST' => 'California', 'L' => 'San Francisco', 'O' => 'TrustCorp', 'CN' => 'TrustCorp Global Root CA v3'],
					],
					[
						'name' => '/C=US/ST=California/L=San Francisco/O=TrustCorp/OU=TrustCorp Government Solutions/CN=TrustCorp Business Intermediate CA v2',
						'subject' => ['C' => 'US', 'ST' => 'California', 'L' => 'San Francisco', 'O' => 'TrustCorp', 'OU' => 'TrustCorp Government Solutions', 'CN' => 'TrustCorp Business Intermediate CA v2'],
						'issuer' => ['C' => 'US', 'ST' => 'California', 'L' => 'San Francisco', 'O' => 'TrustCorp', 'OU' => 'TrustCorp Global Root CA v3', 'CN' => 'TrustCorp Government Intermediate CA v2'],
					],
				],
				[
					'valid' => true,
					'hasRoot' => true,
					'isComplete' => true,
					'length' => 3,
				],
			],
		];
	}

	public function testDuplicateCertificateNames(): void {
		$certificates = [
			[
				'name' => '/CN=Duplicate',
				'subject' => ['CN' => 'Duplicate'],
				'issuer' => ['CN' => 'Root'],
			],
			[
				'name' => '/CN=Duplicate',
				'subject' => ['CN' => 'Different'],
				'issuer' => ['CN' => 'Root'],
			],
		];

		$this->expectExceptionMessage('Duplicate certificate names detected');
		$this->orderCertificates->orderCertificates($certificates);
	}

	public function testRealChainFromUser(): void {
		$realChain = [
			[
				'field' => 'Signature1',
				'subject' => [
					'CN' => 'SecureSign Digital Solutions Inc:98765432100123',
					'OU' => ['Business Certificate A1', 'TrustCorp Government Solutions', '98765432100123', 'Digital Signatures'],
					'L' => 'San Francisco',
					'ST' => 'California',
					'O' => 'TrustCorp',
					'C' => 'US'
				],
				'name' => '/C=US/ST=California/L=San Francisco/O=TrustCorp/OU=TrustCorp Global Root CA v3/CN=TrustCorp Government Intermediate CA v2',
				'issuer' => [
					'C' => 'US',
					'O' => 'TrustCorp',
					'ST' => 'California',
					'L' => 'San Francisco',
					'CN' => 'TrustCorp Global Root CA v3'
				]
			],
			[
				'name' => '/C=US/ST=California/L=San Francisco/O=TrustCorp/CN=TrustCorp Global Root CA v3',
				'subject' => [
					'C' => 'US',
					'O' => 'TrustCorp',
					'ST' => 'California',
					'L' => 'San Francisco',
					'CN' => 'TrustCorp Global Root CA v3'
				],
				'issuer' => [
					'C' => 'US',
					'O' => 'TrustCorp',
					'ST' => 'California',
					'L' => 'San Francisco',
					'CN' => 'TrustCorp Global Root CA v3'
				]
			],
			[
				'name' => '/C=US/ST=California/L=San Francisco/O=TrustCorp/OU=TrustCorp Government Solutions/CN=TrustCorp Business Intermediate CA v2',
				'subject' => [
					'C' => 'US',
					'O' => 'TrustCorp',
					'ST' => 'California',
					'L' => 'San Francisco',
					'OU' => 'TrustCorp Government Solutions',
					'CN' => 'TrustCorp Business Intermediate CA v2'
				],
				'issuer' => [
					'C' => 'US',
					'O' => 'TrustCorp',
					'ST' => 'California',
					'L' => 'San Francisco',
					'OU' => 'TrustCorp Global Root CA v3',
					'CN' => 'TrustCorp Government Intermediate CA v2'
				]
			],
			[
				'name' => '/C=US/ST=California/L=San Francisco/O=TrustCorp/OU=Digital Signatures/OU=98765432100123/OU=TrustCorp Government Solutions/OU=Business Certificate A1/CN=SecureSign Digital Solutions Inc:98765432100123',
				'subject' => [
					'C' => 'US',
					'O' => 'TrustCorp',
					'ST' => 'California',
					'L' => 'San Francisco',
					'OU' => ['Digital Signatures', '98765432100123', 'TrustCorp Government Solutions', 'Business Certificate A1'],
					'CN' => 'SecureSign Digital Solutions Inc:98765432100123'
				],
				'issuer' => [
					'C' => 'US',
					'O' => 'TrustCorp',
					'ST' => 'California',
					'L' => 'San Francisco',
					'OU' => 'TrustCorp Government Solutions',
					'CN' => 'TrustCorp Business Intermediate CA v2'
				]
			]
		];

		$result = $this->orderCertificates->orderCertificates($realChain);

		$this->assertCount(4, $result);
		$this->assertEquals('SecureSign Digital Solutions Inc:98765432100123', $result[0]['subject']['CN']);
	}

	public function testUserRealIssue(): void {
		$userChain = [
			[
				'name' => '/C=US/ST=California/L=San Francisco/O=TrustCorp/OU=TrustCorp Global Root CA v3/CN=TrustCorp Government Intermediate CA v2',
				'subject' => ['C' => 'US', 'ST' => 'California', 'L' => 'San Francisco', 'O' => 'TrustCorp', 'OU' => 'TrustCorp Global Root CA v3', 'CN' => 'TrustCorp Government Intermediate CA v2'],
				'issuer' => ['C' => 'US', 'ST' => 'California', 'L' => 'San Francisco', 'O' => 'TrustCorp', 'CN' => 'TrustCorp Global Root CA v3'],
			],
			[
				'name' => '/C=US/ST=California/L=San Francisco/O=TrustCorp/CN=TrustCorp Global Root CA v3',
				'subject' => ['C' => 'US', 'ST' => 'California', 'L' => 'San Francisco', 'O' => 'TrustCorp', 'CN' => 'TrustCorp Global Root CA v3'],
				'issuer' => ['C' => 'US', 'ST' => 'California', 'L' => 'San Francisco', 'O' => 'TrustCorp', 'CN' => 'TrustCorp Global Root CA v3'],
			],
			[
				'name' => '/C=US/ST=California/L=San Francisco/O=TrustCorp/OU=TrustCorp Government Solutions/CN=TrustCorp Business Intermediate CA v2',
				'subject' => ['C' => 'US', 'ST' => 'California', 'L' => 'San Francisco', 'O' => 'TrustCorp', 'OU' => 'TrustCorp Government Solutions', 'CN' => 'TrustCorp Business Intermediate CA v2'],
				'issuer' => ['C' => 'US', 'ST' => 'California', 'L' => 'San Francisco', 'O' => 'TrustCorp', 'OU' => 'TrustCorp Global Root CA v3', 'CN' => 'TrustCorp Government Intermediate CA v2'],
			],
			[
				'name' => '/C=US/ST=California/L=San Francisco/O=TrustCorp/OU=Digital Signatures/OU=98765432100123/OU=TrustCorp Government Solutions/OU=Business Certificate A1/CN=SecureSign Digital Solutions Inc:98765432100123',
				'subject' => ['C' => 'US', 'ST' => 'California', 'L' => 'San Francisco', 'O' => 'TrustCorp', 'OU' => 'Digital Signatures', 'CN' => 'SecureSign Digital Solutions Inc:98765432100123'],
				'issuer' => ['C' => 'US', 'ST' => 'California', 'L' => 'San Francisco', 'O' => 'TrustCorp', 'OU' => 'TrustCorp Government Solutions', 'CN' => 'TrustCorp Business Intermediate CA v2'],
			],
		];

		$result = $this->orderCertificates->orderCertificates($userChain);

		$this->assertCount(4, $result);
		$this->assertStringContainsString('SecureSign', $result[0]['subject']['CN']);
		$this->assertEquals('TrustCorp Business Intermediate CA v2', $result[1]['subject']['CN']);
		$this->assertEquals('TrustCorp Government Intermediate CA v2', $result[2]['subject']['CN']);
		$this->assertEquals('TrustCorp Global Root CA v3', $result[3]['subject']['CN']);
	}

	public function testNormalizeDistinguishedName(): void {
		$cert1 = [
			'name' => '/C=BR/O=Test/CN=Test',
			'subject' => ['CN' => 'Test', 'O' => 'Test', 'C' => 'BR'],
			'issuer' => ['CN' => 'Root', 'O' => 'Test', 'C' => 'BR'],
		];

		$cert2 = [
			'name' => '/C=BR/O=Test/CN=Root',
			'subject' => ['C' => 'BR', 'O' => 'Test', 'CN' => 'Root'],
			'issuer' => ['C' => 'BR', 'O' => 'Test', 'CN' => 'Root'],
		];

		$result = $this->orderCertificates->orderCertificates([$cert1, $cert2]);

		$this->assertCount(2, $result);
		$this->assertEquals('Test', $result[0]['subject']['CN']);
		$this->assertEquals('Root', $result[1]['subject']['CN']);
	}

	public function testValidateChainExtensionsWithValidCertificates(): void {
		$tempDir = sys_get_temp_dir() . '/cert_test_' . uniqid();
		mkdir($tempDir, 0755, true);

		try {
			$caKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
			$caCsr = openssl_csr_new(['CN' => 'Test CA'], $caKey, ['digest_alg' => 'sha256']);

			$caConfig = "[v3_ca]\n"
						. "basicConstraints = critical, CA:TRUE\n"
						. "keyUsage = critical, digitalSignature, keyCertSign\n"
						. "authorityKeyIdentifier = keyid:always\n"
						. 'subjectKeyIdentifier = hash';

			file_put_contents($tempDir . '/ca_config.cnf', $caConfig);
			$caCert = openssl_csr_sign($caCsr, null, $caKey, 365, [
				'digest_alg' => 'sha256',
				'config' => $tempDir . '/ca_config.cnf',
				'x509_extensions' => 'v3_ca'
			]);

			openssl_x509_export($caCert, $caPem);
			file_put_contents($tempDir . '/ca.pem', $caPem);

			$leafKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
			$leafCsr = openssl_csr_new(['CN' => 'Test Leaf'], $leafKey, ['digest_alg' => 'sha256']);

			$leafConfig = "[v3_req]\n"
						  . "basicConstraints = critical, CA:FALSE\n"
						  . "keyUsage = critical, digitalSignature, keyEncipherment, nonRepudiation\n"
						  . "authorityKeyIdentifier = keyid:always,issuer:always\n"
						  . 'subjectKeyIdentifier = hash';

			file_put_contents($tempDir . '/leaf_config.cnf', $leafConfig);
			$leafCert = openssl_csr_sign($leafCsr, $caCert, $caKey, 365, [
				'digest_alg' => 'sha256',
				'config' => $tempDir . '/leaf_config.cnf',
				'x509_extensions' => 'v3_req'
			]);

			openssl_x509_export($leafCert, $leafPem);
			file_put_contents($tempDir . '/leaf.pem', $leafPem);

			$certificates = [
				[
					'name' => $tempDir . '/leaf.pem',
					'subject' => ['CN' => 'Test Leaf'],
					'issuer' => ['CN' => 'Test CA']
				],
				[
					'name' => $tempDir . '/ca.pem',
					'subject' => ['CN' => 'Test CA'],
					'issuer' => ['CN' => 'Test CA']
				]
			];

			$result = $this->orderCertificates->validateChainExtensions($certificates);

			$this->assertTrue($result['valid'], 'Certificate chain should be valid');
			$this->assertEmpty($result['errors'], 'No errors should be present');
			$this->assertCount(2, $result['certificates'], 'Should validate 2 certificates');

			foreach ($result['certificates'] as $certResult) {
				$this->assertTrue($certResult['valid'], 'Each certificate should be valid');
				$this->assertArrayHasKey('extensions', $certResult);
				$this->assertArrayHasKey('basicConstraints', $certResult['extensions']);
				$this->assertArrayHasKey('keyUsage', $certResult['extensions']);
				$this->assertArrayHasKey('subjectKeyIdentifier', $certResult['extensions']);
			}
		} finally {
			if (file_exists($tempDir . '/ca.pem')) {
				unlink($tempDir . '/ca.pem');
			}
			if (file_exists($tempDir . '/leaf.pem')) {
				unlink($tempDir . '/leaf.pem');
			}
			if (file_exists($tempDir . '/ca_config.cnf')) {
				unlink($tempDir . '/ca_config.cnf');
			}
			if (file_exists($tempDir . '/leaf_config.cnf')) {
				unlink($tempDir . '/leaf_config.cnf');
			}
			if (is_dir($tempDir)) {
				rmdir($tempDir);
			}
		}
	}

	public function testValidateChainExtensionsWithInvalidExtensions(): void {
		$tempDir = sys_get_temp_dir() . '/cert_test_invalid_' . uniqid();
		mkdir($tempDir, 0755, true);

		try {
			$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
			$csr = openssl_csr_new(['CN' => 'Invalid Cert'], $key, ['digest_alg' => 'sha256']);

			$invalidConfig = "[v3_req]\n"
							 . "basicConstraints = CA:TRUE\n"
							 . 'keyUsage = dataEncipherment';

			file_put_contents($tempDir . '/invalid_config.cnf', $invalidConfig);
			$cert = openssl_csr_sign($csr, null, $key, 365, [
				'digest_alg' => 'sha256',
				'config' => $tempDir . '/invalid_config.cnf',
				'x509_extensions' => 'v3_req'
			]);

			openssl_x509_export($cert, $certPem);
			file_put_contents($tempDir . '/invalid.pem', $certPem);

			$certificates = [
				[
					'name' => $tempDir . '/invalid.pem',
					'subject' => ['CN' => 'Invalid Cert'],
					'issuer' => ['CN' => 'Invalid Cert']
				]
			];

			$result = $this->orderCertificates->validateChainExtensions($certificates);

			$this->assertFalse($result['valid'], 'Certificate should be invalid due to improper extensions');
			$this->assertNotEmpty($result['errors'], 'Errors should be present for invalid extensions');
		} finally {
			if (file_exists($tempDir . '/invalid.pem')) {
				unlink($tempDir . '/invalid.pem');
			}
			if (file_exists($tempDir . '/invalid_config.cnf')) {
				unlink($tempDir . '/invalid_config.cnf');
			}
			if (is_dir($tempDir)) {
				rmdir($tempDir);
			}
		}
	}

	public function testValidateChainExtensionsWithInvalidFile(): void {
		$certificates = [
			[
				'name' => '/nonexistent/file.pem',
				'subject' => ['CN' => 'Test'],
				'issuer' => ['CN' => 'Test']
			]
		];

		$result = $this->orderCertificates->validateChainExtensions($certificates);

		$this->assertFalse($result['valid']);
		$this->assertNotEmpty($result['errors']);
		$this->assertStringContainsString('Certificate file not found', $result['errors'][0]);
	}

	public function testValidateChainExtensionsWithMissingName(): void {
		$certificates = [
			[
				'subject' => ['CN' => 'Test'],
				'issuer' => ['CN' => 'Test']
			]
		];

		$result = $this->orderCertificates->validateChainExtensions($certificates);

		$this->assertFalse($result['valid']);
		$this->assertContains('Certificate missing name field', $result['errors']);
	}

	public function testOrderCertificatesWithAkiSkiValidation(): void {
		$tempDir = sys_get_temp_dir() . '/aki_ski_ordering_' . uniqid();
		mkdir($tempDir, 0755, true);

		try {
			$rootKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
			$rootCsr = openssl_csr_new(['CN' => 'Root CA'], $rootKey, ['digest_alg' => 'sha256']);

			$rootConfig = "[v3_ca]\n"
						  . "basicConstraints = critical, CA:TRUE\n"
						  . "keyUsage = critical, digitalSignature, keyCertSign\n"
						  . "subjectKeyIdentifier = hash\n"
						  . 'authorityKeyIdentifier = keyid:always';

			file_put_contents($tempDir . '/root_config.cnf', $rootConfig);
			$rootCert = openssl_csr_sign($rootCsr, null, $rootKey, 365, [
				'digest_alg' => 'sha256',
				'config' => $tempDir . '/root_config.cnf',
				'x509_extensions' => 'v3_ca'
			]);

			openssl_x509_export($rootCert, $rootPem);
			file_put_contents($tempDir . '/root.pem', $rootPem);

			$leafKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
			$leafCsr = openssl_csr_new(['CN' => 'Leaf Certificate'], $leafKey, ['digest_alg' => 'sha256']);

			$leafConfig = "[v3_req]\n"
						  . "basicConstraints = critical, CA:FALSE\n"
						  . "keyUsage = critical, digitalSignature, keyEncipherment, nonRepudiation\n"
						  . "subjectKeyIdentifier = hash\n"
						  . 'authorityKeyIdentifier = keyid:always,issuer:always';

			file_put_contents($tempDir . '/leaf_config.cnf', $leafConfig);
			$leafCert = openssl_csr_sign($leafCsr, $rootCert, $rootKey, 365, [
				'digest_alg' => 'sha256',
				'config' => $tempDir . '/leaf_config.cnf',
				'x509_extensions' => 'v3_req'
			]);

			openssl_x509_export($leafCert, $leafPem);
			file_put_contents($tempDir . '/leaf.pem', $leafPem);

			$certificates = [
				[
					'name' => $tempDir . '/root.pem',
					'subject' => ['CN' => 'Root CA'],
					'issuer' => ['CN' => 'Root CA']
				],
				[
					'name' => $tempDir . '/leaf.pem',
					'subject' => ['CN' => 'Leaf Certificate'],
					'issuer' => ['CN' => 'Root CA']
				]
			];

			$ordered = $this->orderCertificates->orderCertificates($certificates);

			$this->assertCount(2, $ordered, 'Should have 2 certificates');
			$this->assertEquals('Leaf Certificate', $ordered[0]['subject']['CN'], 'Leaf should be first');
			$this->assertEquals('Root CA', $ordered[1]['subject']['CN'], 'Root should be second');

			$validation = $this->orderCertificates->validateChainExtensions($ordered);
			$this->assertTrue($validation['valid'], 'Chain should be valid with proper AKI/SKI');
		} finally {
			if (file_exists($tempDir . '/root.pem')) {
				unlink($tempDir . '/root.pem');
			}
			if (file_exists($tempDir . '/leaf.pem')) {
				unlink($tempDir . '/leaf.pem');
			}
			if (file_exists($tempDir . '/root_config.cnf')) {
				unlink($tempDir . '/root_config.cnf');
			}
			if (file_exists($tempDir . '/leaf_config.cnf')) {
				unlink($tempDir . '/leaf_config.cnf');
			}
			if (is_dir($tempDir)) {
				rmdir($tempDir);
			}
		}
	}
}
