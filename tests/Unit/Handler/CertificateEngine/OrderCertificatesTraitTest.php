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
	public function testIncompleteCertificateChain($certList): void {
		$this->expectExceptionMessage('Certificate chain is incomplete or invalid.');
		$this->orderCertificates->orderCertificates($certList);
	}

	public static function dataIncompleteCertificateChain(): array {
		return [
			[
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
			[
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
		];
	}
}
