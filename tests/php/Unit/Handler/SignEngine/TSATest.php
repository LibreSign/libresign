<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Handler\SignEngine;

use OCA\Libresign\Handler\SignEngine\TSA;
use OCA\Libresign\Vendor\phpseclib3\File\ASN1;
use PHPUnit\Framework\TestCase;

class TSATest extends TestCase {
	private TSA $tsa;

	protected function setUp(): void {
		parent::setUp();
		$this->tsa = new TSA();
	}

	public function testConstructorLoadsOIDs(): void {
		$tsa = new TSA();

		$this->assertEquals('2.16.840.1.101.3.4.2.1', ASN1::getOID('id-sha256'));
		$this->assertEquals('1.3.14.3.2.26', ASN1::getOID('id-sha1'));
	}

	public function testExtractWithEmptyArray(): void {
		$result = $this->tsa->extract([]);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('genTime', $result);
		$this->assertArrayHasKey('policy', $result);
		$this->assertArrayHasKey('serialNumber', $result);
		$this->assertArrayHasKey('cnHints', $result);
		$this->assertArrayHasKey('displayName', $result);

		$this->assertNull($result['policy']);
		$this->assertNull($result['serialNumber']);
		$this->assertEquals([], $result['cnHints']);
	}

	public function testGetSigninTimeWithNoSigningTime(): void {
		$result = $this->tsa->getSigninTime([]);
		$this->assertNull($result);
	}

	public function testGetSigninTimeWithInvalidStructure(): void {
		$invalidStructure = [
			[
				'type' => ASN1::TYPE_SEQUENCE,
				'content' => 'invalid',
			]
		];

		$result = $this->tsa->getSigninTime($invalidStructure);
		$this->assertNull($result);
	}

	public function testExtractWithMinimalValidStructure(): void {
		$mockTstInfo = $this->createMockTstInfo();

		$result = $this->tsa->extract($mockTstInfo);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('genTime', $result);
		$this->assertArrayHasKey('policy', $result);
		$this->assertArrayHasKey('serialNumber', $result);
		$this->assertArrayHasKey('cnHints', $result);
		$this->assertArrayHasKey('displayName', $result);
	}

	public function testExtractWithMalformedData(): void {
		$malformedData = [
			[
				'type' => 'invalid_type',
				'content' => 'malformed_content',
			]
		];

		$result = $this->tsa->extract($malformedData);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('genTime', $result);
	}

	public function testDisplayNameGeneration(): void {
		$mockStructureWithCN = $this->createMockStructureWithCommonName();

		$result = $this->tsa->extract($mockStructureWithCN);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('displayName', $result);
		$this->assertIsString($result['displayName']);
	}

	private function createMockTstInfo(): array {
		return [
			[
				'type' => ASN1::TYPE_SEQUENCE,
				'content' => [
					[
						'type' => ASN1::TYPE_OBJECT_IDENTIFIER,
						'content' => '1.2.840.113549.1.9.16.2.14' // id-aa-timeStampToken
					],
					[
						'type' => ASN1::TYPE_SET,
						'content' => [
							[
								'type' => ASN1::TYPE_SEQUENCE,
								'content' => []
							]
						]
					]
				]
			]
		];
	}

	private function createMockStructureWithCommonName(): array {
		return [
			[
				'type' => ASN1::TYPE_SEQUENCE,
				'content' => [
					[
						'type' => ASN1::TYPE_OBJECT_IDENTIFIER,
						'content' => '2.5.4.3' // commonName OID
					],
					[
						'type' => ASN1::TYPE_UTF8_STRING,
						'content' => 'Test CA',
					]
				]
			]
		];
	}

	public function testSerialNumberHandling(): void {
		$result = $this->tsa->extract([]);

		$this->assertNull($result['serialNumber']);
	}

	public function testFallbackParsingWithEmptyData(): void {
		$result = $this->tsa->extract([
			[
				'type' => 'invalid',
				'content' => null
			]
		]);

		$this->assertIsArray($result);
		$this->assertNull($result['genTime']);
		$this->assertIsArray($result['cnHints']);
		$this->assertIsString($result['displayName']);
	}

	public function testOIDMapping(): void {
		$structureWithMultipleOIDs = [
			[
				'type' => ASN1::TYPE_SEQUENCE,
				'content' => [
					[
						'type' => ASN1::TYPE_OBJECT_IDENTIFIER,
						'content' => '2.5.4.6', // countryName
					],
					[
						'type' => ASN1::TYPE_PRINTABLE_STRING,
						'content' => 'US',
					],
					[
						'type' => ASN1::TYPE_OBJECT_IDENTIFIER,
						'content' => '2.5.4.10', // organizationName
					],
					[
						'type' => ASN1::TYPE_UTF8_STRING,
						'content' => 'Test Organization',
					]
				]
			]
		];

		$result = $this->tsa->extract($structureWithMultipleOIDs);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('cnHints', $result);
		$this->assertIsArray($result['cnHints']);
	}
}
