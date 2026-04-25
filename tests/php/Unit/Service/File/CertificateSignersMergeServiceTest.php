<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use DateTime;
use OCA\Libresign\Service\File\CertificateSignersMergeService;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CertificateSignersMergeServiceTest extends TestCase {
	private function getService(): CertificateSignersMergeService {
		return new CertificateSignersMergeService();
	}

	public function testMergeSkipsUnmatchedTsaSignerWhenContractSignerExists(): void {
		$fileData = new \stdClass();
		$fileData->signers = [
			(object)[
				'signRequestId' => 1,
				'uid' => 'whatsapp:+5500000000',
				'displayName' => 'Contract Signer',
			],
		];

		$certData = [
			[
				'chain' => [
					[
						'subject' => [
							'UID' => 'whatsapp:+5500000000',
							'CN' => 'Contract Signer',
						],
					],
				],
			],
			[
				'timestamp' => [
					'genTime' => new DateTime('2026-04-25T18:36:28Z'),
				],
				'chain' => [
					[
						'subject' => ['CN' => 'www.freetsa.org'],
					],
				],
			],
		];

		$this->getService()->merge(
			$fileData,
			$certData,
			'example.com',
			'Signed',
			fn (array $cert, string $host): ?string => $cert['subject']['UID'] ?? null,
			fn (string $method, string $value): string => $method . ':' . $value,
			fn (string $accountId): ?string => null,
		);

		$this->assertCount(1, $fileData->signers);
		$this->assertArrayHasKey('timestamp', (array)$fileData->signers[0]);
		$this->assertSame('2026-04-25T18:36:28+00:00', $fileData->signers[0]->timestamp['genTime']);
		$this->assertObjectNotHasProperty('tsa', $fileData);
	}

	#[DataProvider('providerDisplayNameResolution')]
	public function testMergeResolvesDisplayNameForAccountUid(?string $accountDisplayName, string $expected): void {
		$fileData = new \stdClass();

		$certData = [
			[
				'uid' => 'account:admin',
				'chain' => [
					[
						'name' => 'Admin Cert',
						'subject' => ['CN' => 'admin'],
					],
				],
			],
		];

		$this->getService()->merge(
			$fileData,
			$certData,
			'example.com',
			'Signed',
			fn (array $cert, string $host): ?string => null,
			fn (string $method, string $value): string => $method . ':' . $value,
			fn (string $accountId): ?string => $accountDisplayName,
		);

		$this->assertSame($expected, $fileData->signers[0]->displayName);
	}

	public static function providerDisplayNameResolution(): array {
		return [
			'display name from lookup' => ['Admin User', 'Admin User'],
			'fallback to account id' => [null, 'admin'],
		];
	}

	#[DataProvider('providerCertificateInfoMatching')]
	public function testMergeMatchesExistingSignerByCertificateInfo(string $certificateField, string $value): void {
		$fileData = new \stdClass();
		$fileData->signers = [
			(object)[
				'signRequestId' => 88,
				'uid' => 'account:existing',
				'displayName' => 'Existing Display Name',
				'metadata' => [
					'certificate_info' => [
						$certificateField => $value,
					],
				],
			],
		];

		$certData = [
			[
				'chain' => [
					[
						$certificateField => $value,
						'subject' => ['CN' => 'Certificate CN'],
					],
				],
			],
		];

		$this->getService()->merge(
			$fileData,
			$certData,
			'example.com',
			'Signed',
			fn (array $cert, string $host): ?string => 'resolved:uid',
			fn (string $method, string $rawValue): string => $method . ':' . $rawValue,
			fn (string $accountId): ?string => 'Overwritten Name',
		);

		$this->assertCount(1, $fileData->signers);
		$this->assertSame('account:existing', $fileData->signers[0]->uid);
		$this->assertSame('Existing Display Name', $fileData->signers[0]->displayName);
		$this->assertSame(2, $fileData->signers[0]->status);
	}

	public static function providerCertificateInfoMatching(): array {
		return [
			'match by serial number' => ['serialNumber', '1234567890'],
			'match by serial number hex' => ['serialNumberHex', 'ABCDEF01'],
			'match by hash' => ['hash', 'deadbeefcafebabe'],
		];
	}

	public function testMergeDoesNotExportTopLevelTsaWithTimestampData(): void {
		$fileData = new \stdClass();
		$fileData->signers = [];

		$certData = [[
			'uid' => 'email:signer@example.com',
			'timestamp' => [
				'genTime' => new DateTime('2026-01-01T00:00:00Z'),
				'cnHints' => ['commonName' => 'tsa.example.org'],
			],
			'chain' => [[
				'subject' => ['CN' => 'Signer User'],
			]],
		]];

		$this->getService()->merge(
			$fileData,
			$certData,
			'example.com',
			'Signed',
			fn (array $cert, string $host): ?string => null,
			fn (string $method, string $value): string => $method . ':' . $value,
			fn (string $accountId): ?string => null,
		);

		$this->assertCount(1, $fileData->signers);
		$this->assertArrayHasKey('timestamp', (array)$fileData->signers[0]);
		$this->assertObjectNotHasProperty('tsa', $fileData);
	}
}
