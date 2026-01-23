<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use DateTime;
use DateTimeInterface;
use OCA\Libresign\Service\File\SignersLoader;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SignersLoaderTest extends TestCase {
	private function getService($signRequestMapper = null, $identifyMethodService = null): SignersLoader {
		$signRequestMapper ??= $this->createMock(\OCA\Libresign\Db\SignRequestMapper::class);
		$identifyMethodService ??= $this->createMock(\OCA\Libresign\Service\IdentifyMethodService::class);
		$accountManager = $this->createMock(\OCP\Accounts\IAccountManager::class);
		$userManager = $this->createMock(\OCP\IUserManager::class);

		return new SignersLoader($signRequestMapper, $identifyMethodService, $accountManager, $userManager);
	}

	#[DataProvider('dataLoadSignersFromCertData')]
	public function testLoadSignersFromCertData(array $certData, string $host, string $resolveUidReturn, array $expected): void {
		$signRequestMapper = $this->createMock(\OCA\Libresign\Db\SignRequestMapper::class);
		$signRequestMapper->method('getTextOfSignerStatus')->willReturn('status-text');

		$identifyMethodService = $this->createMock(\OCA\Libresign\Service\IdentifyMethodService::class);
		$identifyMethodService->method('resolveUid')->willReturn($resolveUidReturn);

		$service = $this->getService($signRequestMapper, $identifyMethodService);

		$fileData = new \stdClass();

		$service->loadSignersFromCertData($fileData, $certData, $host);

		$this->assertTrue(isset($fileData->signers), 'signers not set');
		$this->assertIsArray($fileData->signers);
		foreach ($expected as $index => $checks) {
			$this->assertArrayHasKey($index, $fileData->signers);
			$signer = $fileData->signers[$index];

			if (isset($checks['status'])) {
				$this->assertEquals($checks['status'], $signer->status);
			}
			if (isset($checks['statusText'])) {
				$this->assertEquals($checks['statusText'], $signer->statusText);
			}
			if (isset($checks['uid'])) {
				$this->assertEquals($checks['uid'], $signer->uid);
			}
			if (isset($checks['displayName'])) {
				$this->assertEquals($checks['displayName'], $signer->displayName);
			}
			if (isset($checks['signed'])) {
				$this->assertEquals($checks['signed'], $signer->signed);
			}
			if (isset($checks['timestamp_genTime'])) {
				$this->assertEquals($checks['timestamp_genTime'], $signer->timestamp['genTime']);
			}
			if (isset($checks['chain_displayName'])) {
				$this->assertEquals($checks['chain_displayName'], $signer->chain[0]['displayName']);
			}
			if (isset($checks['chain_valid_from'])) {
				$this->assertEquals($checks['chain_valid_from'], $signer->chain[0]['valid_from']);
			}
			if (isset($checks['chain_valid_to'])) {
				$this->assertEquals($checks['chain_valid_to'], $signer->chain[0]['valid_to']);
			}
		}
	}

	public static function dataLoadSignersFromCertData(): array {
		return [
			'chain with numeric timestamps' => [
				[
					[
						'chain' => [
							[
								'name' => 'CA Root',
								'subject' => ['CN' => 'CA Root CN'],
								'validFrom_time_t' => 1609459200, // 2021-01-01
								'validTo_time_t' => 1640995200,   // 2022-01-01
							],
						],
					],
				],
				'example.com',
				'cert:resolved',
				[
					0 => [
						'status' => 2,
						'statusText' => 'status-text',
						'uid' => 'cert:resolved',
						'displayName' => 'CA Root',
						'chain_displayName' => 'CA Root',
						'chain_valid_from' => (new DateTime('@1609459200', new \DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
						'chain_valid_to' => (new DateTime('@1640995200', new \DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
					],
				],
			],
			'timestamp and signingTime as DateTime plus explicit uid' => [
				[
					[
						'timestamp' => ['genTime' => new DateTime('2023-01-01T12:00:00Z')],
						'signingTime' => new DateTime('2023-02-02T10:00:00Z'),
						'chain' => [ [ 'subject' => ['CN' => 'User CN'] ] ],
						'uid' => 'explicit:uid',
					],
				],
				'example.org',
				'should-not-be-used',
				[
					0 => [
						'status' => 2,
						'statusText' => 'status-text',
						'uid' => 'explicit:uid',
						'displayName' => 'User CN',
						'signed' => (new DateTime('2023-02-02T10:00:00Z'))->format(DateTimeInterface::ATOM),
						'timestamp_genTime' => (new DateTime('2023-01-01T12:00:00Z'))->format(DateTimeInterface::ATOM),
						'chain_displayName' => 'User CN',
					],
				],
			],
		];
	}

	public function testLoadSignersFromCertDataMatchesExistingSigner(): void {
		$signRequestMapper = $this->createMock(\OCA\Libresign\Db\SignRequestMapper::class);
		$signRequestMapper->method('getTextOfSignerStatus')->willReturn('status-text');

		$identifyMethodService = $this->createMock(\OCA\Libresign\Service\IdentifyMethodService::class);
		$identifyMethodService->method('resolveUid')->willReturn('account:admin');

		$service = $this->getService($signRequestMapper, $identifyMethodService);

		$fileData = new \stdClass();
		$fileData->signers = [];
		$fileData->signers[0] = (object)[
			'displayName' => 'admin',
			'email' => '',
		];
		$fileData->signers[1] = (object)[
			'displayName' => 'Leon Green',
			'email' => 'leon@example.com',
		];

		$certData = [
			[
				'chain' => [
					[
						'name' => 'Admin Cert',
						'subject' => ['CN' => 'admin'],
					],
				],
			],
		];

		$service->loadSignersFromCertData($fileData, $certData, 'example.com');

		$this->assertCount(2, $fileData->signers);
		$this->assertSame('admin', $fileData->signers[0]->displayName);
		$this->assertSame('Admin Cert', $fileData->signers[0]->chain[0]['displayName']);
		$this->assertObjectNotHasProperty('chain', $fileData->signers[1]);
	}
}
