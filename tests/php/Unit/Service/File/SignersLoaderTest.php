<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use DateTime;
use DateTimeInterface;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\File\SignersLoader;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\Accounts\IAccountManager;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class SignersLoaderTest extends TestCase {
	private SignRequestMapper&MockObject $signRequestMapper;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private IAccountManager&MockObject $accountManager;
	private IUserManager&MockObject $userManager;

	public function setUp(): void {
		parent::setUp();
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->accountManager = $this->createMock(IAccountManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
	}

	private function getService(): SignersLoader {
		return new SignersLoader(
			$this->signRequestMapper,
			$this->identifyMethodService,
			$this->accountManager,
			$this->userManager,
		);
	}

	#[DataProvider('dataLoadSignersFromCertData')]
	public function testLoadSignersFromCertData(array $certData, string $host, string $resolveUidReturn, array $expected): void {
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('status-text');
		$this->identifyMethodService->method('resolveUid')->willReturn($resolveUidReturn);

		$fileData = new \stdClass();

		$this->getService()->loadSignersFromCertData($fileData, $certData, $host);

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
			'LibreSign certificate with isLibreSignRootCA flag' => [
				[
					[
						'chain' => [
							[
								'isLibreSignRootCA' => true,
								'name' => '/C=BR/UID=account:admin/CN=admin',
								'subject' => [
									'C' => 'BR',
									'UID' => 'account:admin',
									'CN' => 'admin',
								],
								'issuer' => ['CN' => 'LibreSign'],
								'hash' => 'abc123',
								'version' => 2,
							],
						],
						'signingTime' => new DateTime('2026-01-28T19:56:57Z'),
					],
				],
				'example.com',
				'not-called-for-libresign',
				[
					0 => [
						'status' => 2,
						'statusText' => 'status-text',
						'uid' => 'account:admin',
						'displayName' => 'admin',
					],
				],
			],
		];
	}

	public function testLoadSignersFromCertDataMatchesExistingSigner(): void {
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('status-text');
		$this->identifyMethodService->method('resolveUid')->willReturn('account:admin');

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

		$this->getService()->loadSignersFromCertData($fileData, $certData, 'example.com');

		$this->assertCount(2, $fileData->signers);
		$this->assertSame('admin', $fileData->signers[0]->displayName);
		$this->assertSame('Admin Cert', $fileData->signers[0]->chain[0]['displayName']);
		$this->assertObjectNotHasProperty('chain', $fileData->signers[1]);
	}

	public function testLoadSignersFromCertDataDeduplicatesByUid(): void {
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('status-text');
		$this->identifyMethodService->method('resolveUid')->willReturn('account:admin');

		$fileData = new \stdClass();
		$certData = [
			[
				'chain' => [
					[
						'name' => '/C=BR/UID=account:admin/CN=admin',
						'subject' => ['CN' => 'admin'],
					],
				],
				'signingTime' => new DateTime('2023-02-02T10:00:00Z'),
			],
			[
				'chain' => [
					[
						'name' => '/C=BR/UID=account:admin/CN=admin',
						'subject' => ['CN' => 'admin'],
					],
				],
				'signingTime' => new DateTime('2023-02-03T10:00:00Z'),
			],
		];

		$this->getService()->loadSignersFromCertData($fileData, $certData, 'example.com');

		$this->assertCount(1, $fileData->signers);
		$this->assertSame('account:admin', $fileData->signers[0]->uid);
	}

	public function testLoadSignersFromCertDataUsesUserDisplayNameForAccountUid(): void {
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('status-text');
		$this->identifyMethodService->method('resolveUid')->willReturn('account:admin');

		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getDisplayName')->willReturn('Admin Display');
		$this->userManager->method('get')->with('admin')->willReturn($user);

		$fileData = new \stdClass();
		$certData = [
			[
				'chain' => [
					[
						'name' => '/C=BR/UID=account:admin/CN=admin',
						'subject' => ['CN' => 'admin'],
					],
				],
			],
		];

		$this->getService()->loadSignersFromCertData($fileData, $certData, 'example.com');

		$this->assertSame('Admin Display', $fileData->signers[0]->displayName);
		$this->assertSame('account:admin', $fileData->signers[0]->uid);
	}



	public function testLoadSignersFromCertDataMatchesLibreSignSignerByUid(): void {
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('Signed');
		$this->identifyMethodService->expects($this->never())->method('resolveUid');

		$fileData = new \stdClass();
		$fileData->signers = [
			(object)[
				'uid' => 'account:admin',
				'displayName' => 'Admin User',
				'signRequestId' => 52,
			],
		];

		$certData = [
			[
				'chain' => [
					[
						'isLibreSignRootCA' => true,
						'name' => '/C=BR/UID=account:admin/CN=admin',
						'subject' => [
							'C' => 'BR',
							'UID' => 'account:admin',
							'CN' => 'admin',
						],
						'issuer' => ['CN' => 'LibreSign'],
						'hash' => 'abc123',
						'version' => 2,
					],
				],
				'signingTime' => new DateTime('2026-01-28T19:56:57Z'),
			],
		];

		$this->getService()->loadSignersFromCertData($fileData, $certData, 'example.com');

		$this->assertCount(1, $fileData->signers);
		$signer = $fileData->signers[0];
		$this->assertSame('Admin User', $signer->displayName);
		$this->assertSame('account:admin', $signer->uid);
		$this->assertTrue(isset($signer->chain));
		$this->assertTrue(isset($signer->name));
	}
}
