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
use OCA\Libresign\Service\SubjectAlternativeNameService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\Accounts\IAccountManager;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class SignersLoaderTest extends TestCase {
	private SignRequestMapper&MockObject $signRequestMapper;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private SubjectAlternativeNameService&MockObject $subjectAlternativeNameService;
	private IAccountManager&MockObject $accountManager;
	private IUserManager&MockObject $userManager;

	public function setUp(): void {
		parent::setUp();
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->subjectAlternativeNameService = $this->createMock(SubjectAlternativeNameService::class);
		$this->accountManager = $this->createMock(IAccountManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
	}

	private function getService(): SignersLoader {
		return new SignersLoader(
			$this->signRequestMapper,
			$this->identifyMethodService,
			$this->subjectAlternativeNameService,
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
			'uid' => 'account:admin',
			'displayName' => 'admin',
			'email' => '',
		];
		$fileData->signers[1] = (object)[
			'uid' => 'account:leon',
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

	public function testLoadSignersFromCertDataPreventsDuplicateFormattedDates(): void {
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('Signed');
		$this->identifyMethodService->expects($this->never())->method('resolveUid');

		$fileData = new \stdClass();

		$certData = [
			[
				'signingTime' => new DateTime('2026-01-28T23:58:51Z'),
				'chain' => [
					[
						'name' => '/C=BR/ST=RJ/L=Rio de Janeiro/O=LibreCode/OU=Libresign, libresign-ca-id:g0sm1ngk87_g:29_e:o/UID=email:leon@example.com/CN=Leon Green (leon@example.com)',
						'subject' => [
							'C' => 'BR',
							'ST' => 'RJ',
							'L' => 'Rio de Janeiro',
							'O' => 'LibreCode',
							'OU' => ['Libresign', 'libresign-ca-id:g0sm1ngk87_g:29_e:o'],
							'UID' => 'email:leon@example.com',
							'CN' => 'Leon Green (leon@example.com)',
						],
						'hash' => '97f8c8c6',
						'issuer' => [
							'C' => 'BR',
							'ST' => 'RJ',
							'L' => 'Rio de Janeiro',
							'O' => 'LibreCode',
							'OU' => ['Libresign', 'libresign-ca-id:g0sm1ngk87_g:29_e:o'],
							'CN' => 'LibreSign',
						],
						'version' => 2,
						'serialNumber' => '3953192966914338552',
						'serialNumberHex' => '36DC900EF9806EF8',
						'validFrom' => '260128235851Z',
						'validTo' => '260129235851Z',
						'validFrom_time_t' => 1769644731,
						'validTo_time_t' => 1769731131,
						'signatureTypeSN' => 'RSA-SHA256',
						'signatureTypeLN' => 'sha256WithRSAEncryption',
						'signatureTypeNID' => 668,
						'purposes' => [
							'1' => [true, false, 'sslclient'],
							'2' => [false, false, 'sslserver'],
						],
						'extensions' => [
							'basicConstraints' => 'CA:FALSE',
							'keyUsage' => 'Digital Signature, Non Repudiation, Key Encipherment',
						],
						// Backend already formatted these dates - this is the problem!
						'valid_from' => 'January 28, 2026, 11:58:51 PM',
						'valid_to' => 'January 29, 2026, 11:58:51 PM',
						'crl_urls' => ['http://localhost/index.php/apps/libresign/crl/libresign_g0sm1ngk87_29_o.crl'],
						'crl_validation' => 'revoked',
						'crl_revoked_at' => '2026-01-28T23:58:53+00:00',
						'signature_validation' => ['id' => 1, 'label' => 'Signature is valid.'],
						'isLibreSignRootCA' => true,
					],
				],
				'docmdp' => [
					'level' => 0,
					'label' => 'No certification',
					'isCertifying' => false,
				],
				'docmdp_validation' => 'DocMDP warning message',
			],
		];

		$this->getService()->loadSignersFromCertData($fileData, $certData, 'localhost');

		$this->assertCount(1, $fileData->signers);
		$signer = $fileData->signers[0];

		// Chain should have ISO formatted dates
		$this->assertSame('2026-01-28T23:58:51+00:00', $signer->chain[0]['valid_from']);
		$this->assertSame('2026-01-29T23:58:51+00:00', $signer->chain[0]['valid_to']);

		// Root level should NOT have the formatted dates from backend
		// These fields should only exist in the chain, not duplicated at root level
		$this->assertObjectNotHasProperty('valid_from', $signer, 'valid_from should not be copied to root level');
		$this->assertObjectNotHasProperty('valid_to', $signer, 'valid_to should not be copied to root level');

		// Also verify other technical fields are not duplicated
		$this->assertObjectNotHasProperty('validFrom_time_t', $signer);
		$this->assertObjectNotHasProperty('validTo_time_t', $signer);
		$this->assertObjectNotHasProperty('purposes', $signer);
		$this->assertObjectNotHasProperty('extensions', $signer);
		$this->assertObjectNotHasProperty('version', $signer);
		$this->assertObjectNotHasProperty('signatureTypeNID', $signer);
		$this->assertObjectNotHasProperty('signatureTypeLN', $signer);

		// But essential fields should be present
		$this->assertSame('97f8c8c6', $signer->hash);
		$this->assertSame('email:leon@example.com', $signer->uid);
		$this->assertSame('2026-01-28T23:58:51+00:00', $signer->signed);
		$this->assertSame('DocMDP warning message', $signer->docmdp_validation);
	}

	#[DataProvider('dataLoadSignersFromCertDataEdgeCases')]
	public function testLoadSignersFromCertDataEdgeCases(
		?array $existingSigners,
		array $certData,
		string $description,
		array $assertions,
	): void {
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('Signed');
		$this->identifyMethodService->expects($this->never())->method('resolveUid');

		$fileData = new \stdClass();
		if ($existingSigners !== null) {
			$fileData->signers = $existingSigners;
		}

		$this->getService()->loadSignersFromCertData($fileData, $certData, 'localhost');

		foreach ($assertions as $assertion) {
			$assertion($fileData->signers);
		}
	}

	public static function dataLoadSignersFromCertDataEdgeCases(): array {
		return [
			'LibreSign UID without prefix' => [
				null,
				[
					[
						'chain' => [
							[
								'isLibreSignRootCA' => true,
								'name' => '/C=BR/UID=admin/CN=admin',
								'subject' => [
									'C' => 'BR',
									'UID' => 'admin',  // No prefix
									'CN' => 'admin',
								],
								'issuer' => ['CN' => 'LibreSign'],
								'hash' => 'abc123',
							],
						],
						'signingTime' => new DateTime('2026-01-28T19:56:57Z'),
					],
				],
				'UID without prefix should have account: added',
				[
					function (array $signers) {
						assert(isset($signers[0]), 'signer should exist');
						assert($signers[0]->uid === 'account:admin', "uid should be 'account:admin', got {$signers[0]->uid}");
					},
				],
			],
			'chain with multiple certificates' => [
				null,
				[
					[
						'chain' => [
							[
								'name' => 'End-Entity Cert',
								'subject' => [
									'CN' => 'User',
									'UID' => 'email:user@example.com',
								],
								'hash' => 'endentity123',
								'serialNumber' => '111',
								'isLibreSignRootCA' => true,
								'crl_validation' => 'valid',
								'validFrom_time_t' => 1609459200,  // 2021-01-01
								'validTo_time_t' => 1640995200,    // 2022-01-01
							],
							[
								'name' => 'CA Cert',
								'subject' => ['CN' => 'LibreSign'],
								'hash' => 'cacert456',
								'serialNumber' => '222',
								'validFrom_time_t' => 1577836800,  // 2020-01-01
								'validTo_time_t' => 1672531200,    // 2023-01-01
							],
						],
						'signingTime' => new DateTime('2026-01-28T19:56:57Z'),
					],
				],
				'only end-entity (chain[0]) fields should enrich root',
				[
					function (array $signers) {
						$signer = $signers[0];
						assert($signer->hash === 'endentity123', 'root hash should be from chain[0]');
						assert($signer->serialNumber === '111', 'root serialNumber should be from chain[0]');
						assert($signer->crl_validation === 'valid', 'root crl_validation should be from chain[0]');
						assert($signer->chain[1]['hash'] === 'cacert456', 'chain[1] should retain its hash');
						assert($signer->chain[1]['serialNumber'] === '222', 'chain[1] should retain its serialNumber');
					},
					function (array $signers) {
						$signer = $signers[0];
						assert($signer->chain[0]['valid_from'] === '2021-01-01T00:00:00+00:00', 'chain[0] valid_from should be ISO');
						assert($signer->chain[0]['valid_to'] === '2022-01-01T00:00:00+00:00', 'chain[0] valid_to should be ISO');
						assert($signer->chain[1]['valid_from'] === '2020-01-01T00:00:00+00:00', 'chain[1] valid_from should be ISO');
						assert($signer->chain[1]['valid_to'] === '2023-01-01T00:00:00+00:00', 'chain[1] valid_to should be ISO');
					},
				],
			],
			'does not overwrite existing fields' => [
				[
					(object)[
						'hash' => 'existing_hash',
						'uid' => 'email:user@example.com',
						'displayName' => 'Existing User',
					],
				],
				[
					[
						'chain' => [
							[
								'isLibreSignRootCA' => true,
								'name' => '/C=BR/UID=email:user@example.com/CN=User',
								'subject' => [
									'UID' => 'email:user@example.com',
									'CN' => 'User',
								],
								'hash' => 'new_hash_from_cert',
								'serialNumber' => '123',
								'issuer' => ['CN' => 'LibreSign'],
							],
						],
						'signingTime' => new DateTime('2026-01-28T19:56:57Z'),
					],
				],
				'should match by uid and not overwrite existing fields',
				[
					function (array $signers) {
						assert(count($signers) === 1, 'should match existing signer');
						$signer = $signers[0];
						assert($signer->hash === 'existing_hash', 'existing hash should NOT be overwritten');
						assert($signer->serialNumber === '123', 'new serialNumber should be added');
						assert($signer->displayName === 'Existing User', 'displayName should be preserved');
					},
				],
			],
		];
	}

	#[DataProvider('dataExternalCertificateMatchingRules')]
	public function testExternalCertificateMatchingRules(
		?object $existingSigner,
		array $certData,
		bool $expectsNewSigner,
		string $expectedMatchedUid,
	): void {
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('Signed');
		$this->identifyMethodService->method('resolveUid')->willReturn('email:external@example.com');

		$fileData = new \stdClass();
		if ($existingSigner) {
			$fileData->signers = [$existingSigner];
		} else {
			$fileData->signers = [];
		}

		$this->getService()->loadSignersFromCertData($fileData, $certData, 'example.com');

		$initialCount = $existingSigner ? 1 : 0;
		$expectedCount = $expectsNewSigner ? $initialCount + 1 : $initialCount;
		$this->assertCount($expectedCount, $fileData->signers);

		if (!$expectsNewSigner && $expectedMatchedUid) {
			$this->assertSame($expectedMatchedUid, $fileData->signers[0]->uid);
		} elseif ($expectsNewSigner && $expectedMatchedUid) {
			$foundNewSigner = false;
			foreach ($fileData->signers as $signer) {
				if ($signer->uid === $expectedMatchedUid) {
					$foundNewSigner = true;
					break;
				}
			}
			$this->assertTrue($foundNewSigner, "Expected to find signer with uid '{$expectedMatchedUid}'");
		}
	}

	public static function dataExternalCertificateMatchingRules(): array {
		return [
			'match by serialNumber (primary rule)' => [
				(object)[
					'uid' => 'external:john',
					'metadata' => ['certificate_info' => ['serialNumber' => '111111']],
				],
				[[
					'chain' => [['subject' => ['CN' => 'John'], 'serialNumber' => '111111', 'serialNumberHex' => 'DIFFERENT', 'hash' => 'different_hash']],
					'signingTime' => new DateTime(),
				]],
				false,
				'external:john',
			],
			'match by serialNumberHex when serialNumber differs' => [
				(object)[
					'uid' => 'external:jane',
					'metadata' => ['certificate_info' => ['serialNumberHex' => 'ABCDEF']],
				],
				[[
					'chain' => [['subject' => ['CN' => 'Jane'], 'serialNumber' => '999999', 'serialNumberHex' => 'ABCDEF', 'hash' => 'different_hash']],
					'signingTime' => new DateTime(),
				]],
				false,
				'external:jane',
			],
			'match by hash as last resort' => [
				(object)[
					'uid' => 'external:bob',
					'metadata' => ['certificate_info' => ['hash' => 'final_hash']],
				],
				[[
					'chain' => [['subject' => ['CN' => 'Bob'], 'hash' => 'final_hash']],
					'signingTime' => new DateTime(),
				]],
				false,
				'external:bob',
			],
			'no match: metadata is null' => [
				(object)[
					'uid' => 'old:signer',
					'metadata' => null,
				],
				[[
					'chain' => [['subject' => ['CN' => 'New'], 'serialNumber' => '111111']],
					'signingTime' => new DateTime(),
				]],
				true,
				'email:external@example.com',
			],
			'no match: metadata certificate_info is empty' => [
				(object)[
					'uid' => 'old:signer2',
					'metadata' => ['certificate_info' => []],
				],
				[[
					'chain' => [['subject' => ['CN' => 'New'], 'serialNumber' => '222222']],
					'signingTime' => new DateTime(),
				]],
				true,
				'email:external@example.com',
			],
			'no match: cert has no identifiers (serialNumber, serialNumberHex, hash)' => [
				(object)[
					'uid' => 'external:alice',
					'metadata' => ['certificate_info' => ['serialNumber' => '333333']],
				],
				[[
					'chain' => [['subject' => ['CN' => 'Alice']]],
					'signingTime' => new DateTime(),
				]],
				true,
				'email:external@example.com',
			],
			'no match: identifiers differ across all fields' => [
				(object)[
					'uid' => 'external:charlie',
					'metadata' => ['certificate_info' => [
						'serialNumber' => '111111',
						'serialNumberHex' => 'AAAAAA',
						'hash' => 'hash_a',
					]],
				],
				[[
					'chain' => [['subject' => ['CN' => 'Charlie'], 'serialNumber' => '222222', 'serialNumberHex' => 'BBBBBB', 'hash' => 'hash_b']],
					'signingTime' => new DateTime(),
				]],
				true,
				'email:external@example.com',
			],
			'match with multiple fields succeeds on first match' => [
				(object)[
					'uid' => 'external:diana',
					'metadata' => ['certificate_info' => [
						'serialNumber' => '444444',
						'serialNumberHex' => 'CCCCCC',
						'hash' => 'matching_hash',
					]],
				],
				[[
					'chain' => [['subject' => ['CN' => 'Diana'], 'serialNumber' => '444444', 'serialNumberHex' => 'CCCCCC', 'hash' => 'matching_hash']],
					'signingTime' => new DateTime(),
				]],
				false,
				'external:diana',
			],
			'match preserves existing signer properties' => [
				(object)[
					'uid' => 'external:eve',
					'displayName' => 'Eve Smith',
					'hash' => 'old_hash',
					'metadata' => ['certificate_info' => ['serialNumber' => '555555']],
				],
				[[
					'chain' => [['subject' => ['CN' => 'Eve'], 'serialNumber' => '555555', 'hash' => 'new_hash_from_pdf']],
					'signingTime' => new DateTime(),
				]],
				false,
				'external:eve',
			],
		];
	}
}
