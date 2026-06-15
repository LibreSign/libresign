<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Crl;

use DateTime;
use OCA\Libresign\Db\Crl;
use OCA\Libresign\Db\CrlMapper;
use OCA\Libresign\Enum\CertificateEngineType;
use OCA\Libresign\Enum\CRLReason;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Service\Certificate\FileService;
use OCA\Libresign\Service\Crl\CrlService;
use OCA\Libresign\Service\Crl\GeneratedCrlStorageService;
use OCA\Libresign\Service\Crl\CrlUrlParserService;
use OCP\ICacheFactory;
use OCP\IMemcache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CrlServiceTest extends TestCase {
	private CrlService $service;
	private CrlMapper&MockObject $crlMapper;
	private LoggerInterface&MockObject $logger;
	private CertificateEngineFactory&MockObject $certificateEngineFactory;
	private CrlUrlParserService&MockObject $crlUrlParserService;
	private FileService&MockObject $certificateFileService;
	private GeneratedCrlStorageService&MockObject $generatedCrlStorage;
	private IMemcache&MockObject $lockCache;
	private ICacheFactory&MockObject $cacheFactory;

	protected function setUp(): void {
		$this->crlMapper = $this->createMock(CrlMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->crlUrlParserService = $this->createMock(CrlUrlParserService::class);
		$this->certificateFileService = $this->createMock(FileService::class);
		$this->generatedCrlStorage = $this->createMock(GeneratedCrlStorageService::class);
		$this->generatedCrlStorage->method('getScopeKey')
			->willReturnCallback(static fn (string $instanceId, int $generation, string $engineType): string => $instanceId . '/' . $generation . '/' . $engineType);
		$this->lockCache = $this->createMock(IMemcache::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->cacheFactory->method('createLocking')->willReturn($this->lockCache);

		$this->service = new CrlService(
			$this->crlMapper,
			$this->logger,
			$this->certificateEngineFactory,
			$this->crlUrlParserService,
			$this->certificateFileService,
			$this->cacheFactory,
			$this->generatedCrlStorage,
		);
	}

	#[DataProvider('rootCertificateFromCrlUrlProvider')]
	public function testGetRootCertificateFromCrlUrlsReturnsCertificate(
		array $crlUrls,
		array $parseResults,
		string $instanceId,
		int $generation,
		CertificateEngineType $engineType,
		string $expectedCert,
	): void {
		$this->crlUrlParserService->expects($this->exactly(count($crlUrls)))
			->method('parseUrl')
			->willReturnCallback(function () use (&$parseResults): ?array {
				return array_shift($parseResults);
			});

		$this->certificateFileService->expects($this->once())
			->method('getRootCertificateByGeneration')
			->with($instanceId, $generation, $engineType)
			->willReturn($expectedCert);

		$result = $this->service->getRootCertificateFromCrlUrls($crlUrls);

		$this->assertSame($expectedCert, $result);
	}

	#[DataProvider('rootCertificateFromCrlUrlEmptyProvider')]
	public function testGetRootCertificateFromCrlUrlsReturnsEmpty(
		array $crlUrls,
		array $parseResults,
	): void {
		if (empty($crlUrls)) {
			$this->crlUrlParserService->expects($this->never())
				->method('parseUrl');
		} else {
			$this->crlUrlParserService->expects($this->exactly(count($crlUrls)))
				->method('parseUrl')
				->willReturnCallback(function () use (&$parseResults): ?array {
					return array_shift($parseResults);
				});
		}

		$this->certificateFileService->expects($this->never())
			->method('getRootCertificateByGeneration');

		$result = $this->service->getRootCertificateFromCrlUrls($crlUrls);

		$this->assertSame('', $result);
	}

	public static function rootCertificateFromCrlUrlProvider(): array {
		return [
			'First CRL URL resolves' => [
				['https://ca.example.com/crl/libresign_inst001_2_o.crl'],
				[[
					'instanceId' => 'inst001',
					'generation' => 2,
					'engineType' => 'o',
				]],
				'inst001',
				2,
				CertificateEngineType::OpenSSL,
				'CERT-OPENSSL-GEN2',
			],
			'Second CRL URL resolves' => [
				['https://ca.example.com/crl/invalid.crl', 'https://ca.example.com/crl/libresign_inst002_5_c.crl'],
				[
					null,
					[
						'instanceId' => 'inst002',
						'generation' => 5,
						'engineType' => 'c',
					],
				],
				'inst002',
				5,
				CertificateEngineType::CFSSL,
				'CERT-CFSSL-GEN5',
			],
		];
	}

	public static function rootCertificateFromCrlUrlEmptyProvider(): array {
		return [
			'No CRL URLs' => [
				[],
				[],
			],
			'All URLs unparseable' => [
				['https://ca.example.com/crl/invalid.crl'],
				[null],
			],
			'Invalid engine type' => [
				['https://ca.example.com/crl/libresign_inst003_1_x.crl'],
				[[
					'instanceId' => 'inst003',
					'generation' => 1,
					'engineType' => 'x',
				]],
			],
		];
	}

	public static function revokeUserCertificatesProvider(): array {
		return [
			'multiple certificates success' => [
				'certificateCount' => 2,
				'failOnIndex' => null,
				'expectedRevoked' => 2,
				'expectWarning' => false,
			],
			'single certificate' => [
				'certificateCount' => 1,
				'failOnIndex' => null,
				'expectedRevoked' => 1,
				'expectWarning' => false,
			],
			'three certificates all success' => [
				'certificateCount' => 3,
				'failOnIndex' => null,
				'expectedRevoked' => 3,
				'expectWarning' => false,
			],
			'partial failure - second fails' => [
				'certificateCount' => 2,
				'failOnIndex' => 2,
				'expectedRevoked' => 1,
				'expectWarning' => true,
			],
			'partial failure - third of three fails' => [
				'certificateCount' => 3,
				'failOnIndex' => 3,
				'expectedRevoked' => 2,
				'expectWarning' => true,
			],
			'no certificates' => [
				'certificateCount' => 0,
				'failOnIndex' => null,
				'expectedRevoked' => 0,
				'expectWarning' => false,
			],
		];
	}

	#[DataProvider('revokeUserCertificatesProvider')]
	public function testRevokeUserCertificates(
		int $certificateCount,
		?int $failOnIndex,
		int $expectedRevoked,
		bool $expectWarning,
	): void {
		$certificates = [];
		for ($i = 1; $i <= $certificateCount; $i++) {
			$cert = new Crl();
			$cert->setSerialNumber((string)(123456 + $i));
			$cert->setInstanceId('test-instance');
			$cert->setGeneration(1);
			$cert->setEngine('openssl');
			$certificates[] = $cert;
		}

		$this->crlMapper->expects($this->once())
			->method('findIssuedByOwner')
			->with('testuser')
			->willReturn($certificates);

		if ($certificateCount > 0) {
			$this->crlMapper->expects($this->exactly($certificateCount))
				->method('getLastCrlNumber')
				->willReturn(0);

			$callCount = 0;
			$this->crlMapper->expects($this->exactly($certificateCount))
				->method('revokeCertificateEntity')
				->willReturnCallback(function ($certificate) use (&$callCount, $failOnIndex) {
					$callCount++;
					if ($failOnIndex !== null && $callCount === $failOnIndex) {
						throw new \Exception('Database error');
					}

					return $certificate;
				});
		}

		$expectedDeleteCalls = $expectedRevoked > 0 ? 1 : 0;
		$this->generatedCrlStorage->expects($this->exactly($expectedDeleteCalls))
			->method('delete')
			->with('test-instance', 1, 'openssl');

		if ($expectWarning) {
			$this->logger->expects($this->once())
				->method('warning')
				->with(
					'Failed to revoke certificate {serial}',
					$this->callback(fn ($context) => isset($context['serial']) && isset($context['error']))
				);
		} else {
			$this->logger->expects($this->never())
				->method('warning');
		}

		$revokedCount = $this->service->revokeUserCertificates(
			'testuser',
			CRLReason::CESSATION_OF_OPERATION,
			'User account deleted',
			'system'
		);

		$this->assertEquals($expectedRevoked, $revokedCount);
	}

	public function testRevokeCertificateWithValidReasonCode(): void {
		$serialNumber = '123456';
		$reason = CRLReason::KEY_COMPROMISE;
		$reasonText = 'Certificate compromised';
		$revokedBy = 'admin';

		$certificate = new Crl();
		$certificate->setInstanceId('test-instance');
		$certificate->setGeneration(1);
		$certificate->setEngine('openssl');

		$this->crlMapper->expects($this->once())
			->method('findBySerialNumber')
			->with($serialNumber)
			->willReturn($certificate);

		$this->crlMapper->expects($this->once())
			->method('getLastCrlNumber')
			->with('test-instance', 1, 'openssl')
			->willReturn(4);

		$this->crlMapper->expects($this->once())
			->method('revokeCertificateEntity')
			->with(
				$certificate,
				CRLReason::KEY_COMPROMISE,
				$reasonText,
				$revokedBy,
				null,
				5,
				null,
			);

		$this->generatedCrlStorage->expects($this->once())
			->method('delete')
			->with('test-instance', 1, 'openssl');

		$result = $this->service->revokeCertificate($serialNumber, $reason, $reasonText, $revokedBy);

		$this->assertTrue($result);
	}

	public function testRevokeCertificateWithoutCrlMetadataFails(): void {
		$serialNumber = '654321';
		$certificate = new Crl();
		$certificate->setSerialNumber($serialNumber);
		$certificate->setEngine('openssl');

		$this->generatedCrlStorage->expects($this->never())->method('delete');

		$this->crlMapper->expects($this->once())
			->method('findBySerialNumber')
			->with($serialNumber)
			->willReturn($certificate);

		$this->crlMapper->expects($this->never())
			->method('getLastCrlNumber');

		$this->crlMapper->expects($this->never())
			->method('revokeCertificateEntity');

		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'Failed to revoke certificate {serial}',
				$this->callback(fn (array $context): bool => $context['serial'] === $serialNumber
					&& isset($context['error'])
					&& $context['error'] !== '')
			);

		$result = $this->service->revokeCertificate($serialNumber);

		$this->assertFalse($result);
	}

	public function testRevokeCertificateDoesNotSwallowErrors(): void {
		$serialNumber = '999999';

		$this->generatedCrlStorage->expects($this->never())->method('delete');

		$this->crlMapper->expects($this->once())
			->method('findBySerialNumber')
			->with($serialNumber)
			->willThrowException(new \Error('boom'));

		$this->logger->expects($this->never())
			->method('warning');

		$this->expectException(\Error::class);
		$this->expectExceptionMessage('boom');

		$this->service->revokeCertificate($serialNumber);
	}

	public function testGenerateCrlDerReturnsPersistedFreshResultWithoutHittingDb(): void {
		$cachedDer = "\x30\x82\x01\x00";

		$this->generatedCrlStorage->expects($this->once())
			->method('read')
			->with('test-instance', 1, 'o')
			->willReturn($cachedDer);
		$this->generatedCrlStorage->method('readMetadata')
			->willReturn([
				'refreshDate' => $this->getCurrentRefreshDate(),
				'generatedAt' => '2026-01-17T10:00:00+00:00',
			]);

		$this->lockCache->expects($this->never())->method('add');
		$this->crlMapper->expects($this->never())->method('getRevokedCertificates');
		$this->certificateEngineFactory->expects($this->never())->method('getEngine');
		$this->generatedCrlStorage->expects($this->never())->method('write');

		$result = $this->service->generateCrlDer('test-instance', 1, 'o');

		$this->assertSame($cachedDer, $result);
	}

	public function testGenerateCrlDerReturnsReusablePersistedResultWhileAnotherRequestRefreshes(): void {
		$cachedDer = "\x30\x82\x01\x00";

		$this->generatedCrlStorage->expects($this->exactly(2))
			->method('read')
			->with('test-instance', 1, 'o')
			->willReturn($cachedDer);
		$this->generatedCrlStorage->method('readMetadata')
			->willReturn([
				'refreshDate' => (new \DateTimeImmutable('yesterday', new \DateTimeZone(date_default_timezone_get())))->format('Y-m-d'),
				'generatedAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
			]);

		$this->lockCache->expects($this->once())
			->method('add')
			->with($this->isType('string'), $this->isType('string'), 60)
			->willReturn(false);

		$this->crlMapper->expects($this->never())->method('getRevokedCertificates');
		$this->certificateEngineFactory->expects($this->never())->method('getEngine');
		$this->generatedCrlStorage->expects($this->never())->method('write');

		$this->assertSame($cachedDer, $this->service->generateCrlDer('test-instance', 1, 'o'));
	}

	public function testGenerateCrlDerGeneratesAndPersistsOnStorageMiss(): void {
		$revokedCertificates = [
			$this->createRevokedCertificateEntity(123456, 'user1@example.com', 1),
			$this->createRevokedCertificateEntity(789012, 'user2@example.com', 2),
		];

		$this->generatedCrlStorage->expects($this->exactly(2))
			->method('read')
			->with('test-instance', 1, 'o')
			->willReturn(null);

		$this->lockCache->expects($this->once())
			->method('add')
			->with($this->isType('string'), $this->isType('string'), 60)
			->willReturn(true);
		$this->lockCache->expects($this->once())
			->method('cad')
			->with($this->isType('string'), $this->isType('string'));

		$this->crlMapper->expects($this->once())
			->method('getRevokedCertificates')
			->with('test-instance', 1, 'o')
			->willReturn($revokedCertificates);

		$mockEngine = $this->createMock(IEngineHandler::class);
		$mockCrlDer = "\x30\x82\x01\x00";
		$mockEngine->expects($this->once())
			->method('generateCrlDer')
			->with($revokedCertificates, 'test-instance', 1, 1)
			->willReturn($mockCrlDer);

		$this->certificateEngineFactory->expects($this->once())
			->method('getEngine')
			->willReturn($mockEngine);

		$this->crlMapper->expects($this->once())
			->method('getLastCrlNumber')
			->with('test-instance', 1, 'openssl')
			->willReturn(0);

		$this->generatedCrlStorage->expects($this->once())
			->method('write')
			->with(
				'test-instance',
				1,
				'o',
				$mockCrlDer,
				$this->callback(function (array $metadata): bool {
					return ($metadata['refreshDate'] ?? null) === $this->getCurrentRefreshDate()
						&& ($metadata['engineType'] ?? null) === 'o'
						&& is_string($metadata['generatedAt'] ?? null)
						&& $metadata['generatedAt'] !== '';
				})
			);

		$result = $this->service->generateCrlDer('test-instance', 1, 'o');

		$this->assertIsString($result);
		$this->assertNotEmpty($result);
		$this->assertEquals(0x30, ord($result[0]));
	}

	public function testGenerateCrlDerDoesNotWriteGeneratedBinaryToDistributedCache(): void {
		$lockCache = $this->createMock(IMemcache::class);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->expects($this->once())
			->method('createLocking')
			->with('libresign_crl_generated')
			->willReturn($lockCache);
		$cacheFactory->expects($this->never())
			->method('createDistributed');

		$service = new CrlService(
			$this->crlMapper,
			$this->logger,
			$this->certificateEngineFactory,
			$this->crlUrlParserService,
			$this->certificateFileService,
			$cacheFactory,
			$this->generatedCrlStorage,
		);

		$mockCrlDer = "\x30\x82\x01\x00";

		$this->generatedCrlStorage->expects($this->exactly(2))
			->method('read')
			->with('test-instance', 1, 'o')
			->willReturn(null);

		$lockCache->expects($this->once())
			->method('add')
			->with($this->isType('string'), $this->isType('string'), 60)
			->willReturn(true);
		$lockCache->expects($this->once())
			->method('cad')
			->with($this->isType('string'), $this->isType('string'));

		$this->crlMapper->expects($this->once())
			->method('getRevokedCertificates')
			->with('test-instance', 1, 'o')
			->willReturn([]);

		$this->crlMapper->expects($this->once())
			->method('getLastCrlNumber')
			->with('test-instance', 1, 'openssl')
			->willReturn(0);

		$mockEngine = $this->createMock(IEngineHandler::class);
		$mockEngine->expects($this->once())
			->method('generateCrlDer')
			->with([], 'test-instance', 1, 1)
			->willReturn($mockCrlDer);

		$this->certificateEngineFactory->expects($this->once())
			->method('getEngine')
			->willReturn($mockEngine);

		$this->generatedCrlStorage->expects($this->once())
			->method('write')
			->with(
				'test-instance',
				1,
				'o',
				$mockCrlDer,
				$this->isType('array')
			);

		$this->assertSame($mockCrlDer, $service->generateCrlDer('test-instance', 1, 'o'));
	}

	public function testGenerateCrlDerReusesPersistedScopeForEngineAliases(): void {
		$persistedEntries = [];
		$persistedMetadata = [];
		$normalizeEngineType = static fn (string $engineType): string => CertificateEngineType::tryFromValue($engineType)?->value ?? $engineType;
		$mockCrlDer = "\x30\x82\x01\x00";

		$this->generatedCrlStorage->expects($this->exactly(3))
			->method('read')
			->willReturnCallback(static function (string $instanceId, int $generation, string $engineType) use (&$persistedEntries, $normalizeEngineType): ?string {
				$scopeKey = $instanceId . '/' . $generation . '/' . $normalizeEngineType($engineType);
				return $persistedEntries[$scopeKey] ?? null;
			});

		$this->generatedCrlStorage->method('readMetadata')
			->willReturnCallback(static function (string $instanceId, int $generation, string $engineType) use (&$persistedMetadata, $normalizeEngineType): ?array {
				$scopeKey = $instanceId . '/' . $generation . '/' . $normalizeEngineType($engineType);
				return $persistedMetadata[$scopeKey] ?? null;
			});

		$this->generatedCrlStorage->expects($this->once())
			->method('write')
			->willReturnCallback(function (string $instanceId, int $generation, string $engineType, string $crlDer, array $metadata) use (&$persistedEntries, &$persistedMetadata, $normalizeEngineType): void {
				$scopeKey = $instanceId . '/' . $generation . '/' . $normalizeEngineType($engineType);
				$persistedEntries[$scopeKey] = $crlDer;
				$persistedMetadata[$scopeKey] = $metadata;
			});

		$this->lockCache->expects($this->once())
			->method('add')
			->with($this->isType('string'), $this->isType('string'), 60)
			->willReturn(true);
		$this->lockCache->expects($this->once())
			->method('cad')
			->with($this->isType('string'), $this->isType('string'));

		$this->crlMapper->expects($this->once())
			->method('getRevokedCertificates')
			->with('test-instance', 1, 'o')
			->willReturn([]);

		$this->crlMapper->expects($this->once())
			->method('getLastCrlNumber')
			->with('test-instance', 1, 'openssl')
			->willReturn(0);

		$mockEngine = $this->createMock(IEngineHandler::class);
		$mockEngine->expects($this->once())
			->method('generateCrlDer')
			->with([], 'test-instance', 1, 1)
			->willReturn($mockCrlDer);

		$this->certificateEngineFactory->expects($this->once())
			->method('getEngine')
			->willReturn($mockEngine);

		$first = $this->service->generateCrlDer('test-instance', 1, 'o');
		$second = $this->service->generateCrlDer('test-instance', 1, 'openssl');

		$this->assertSame($mockCrlDer, $first);
		$this->assertSame($first, $second);
	}

	public function testRefreshGeneratedCrlCacheRefreshesEveryKnownScope(): void {
		$writtenScopes = [];
		$observedEngineNames = [];

		$this->generatedCrlStorage->expects($this->exactly(2))
			->method('read')
			->willReturn(null);

		$this->generatedCrlStorage->expects($this->exactly(2))
			->method('write')
			->willReturnCallback(function (string $instanceId, int $generation, string $engineType, string $crlDer, array $metadata) use (&$writtenScopes): void {
				$writtenScopes[] = $instanceId . '/' . $generation . '/' . $engineType;
			});

		$this->lockCache->expects($this->exactly(2))
			->method('add')
			->with($this->isType('string'), $this->isType('string'), 60)
			->willReturn(true);
		$this->lockCache->expects($this->exactly(2))
			->method('cad')
			->with($this->isType('string'), $this->isType('string'));

		$this->crlMapper->expects($this->once())
			->method('listGeneratedCrlScopes')
			->willReturn([
				['instanceId' => 'instance-a', 'generation' => 1, 'engineType' => 'o'],
				['instanceId' => 'instance-b', 'generation' => 2, 'engineType' => 'c'],
			]);

		$this->crlMapper->expects($this->exactly(2))
			->method('getRevokedCertificates')
			->willReturnOnConsecutiveCalls([], []);

		$this->crlMapper->expects($this->exactly(2))
			->method('getLastCrlNumber')
			->willReturnCallback(function (string $instanceId, int $generation, string $engineName) use (&$observedEngineNames): int {
				$observedEngineNames[] = $engineName;
				return 0;
			});

		$mockEngine = $this->createMock(IEngineHandler::class);
		$mockEngine->expects($this->exactly(2))
			->method('generateCrlDer')
			->willReturnOnConsecutiveCalls("\x30\x82\x01\x00", "\x30\x82\x01\x01");

		$this->certificateEngineFactory->expects($this->exactly(2))
			->method('getEngine')
			->willReturn($mockEngine);

		$this->logger->expects($this->never())->method('warning');

		$refreshedScopes = $this->service->refreshGeneratedCrlCache();

		$this->assertSame(2, $refreshedScopes);
		$this->assertSame(['openssl', 'cfssl'], $observedEngineNames);
		$this->assertSame(['instance-a/1/o', 'instance-b/2/c'], $writtenScopes);
	}

	public function testRefreshGeneratedCrlCacheDoesNotSwallowErrors(): void {
		$this->generatedCrlStorage->expects($this->once())
			->method('read')
			->with('instance-a', 1, 'o')
			->willReturn(null);

		$this->generatedCrlStorage->expects($this->never())->method('write');

		$this->lockCache->expects($this->once())
			->method('add')
			->with($this->isType('string'), $this->isType('string'), 60)
			->willReturn(true);
		$this->lockCache->expects($this->once())
			->method('cad')
			->with($this->isType('string'), $this->isType('string'));

		$this->crlMapper->expects($this->once())
			->method('listGeneratedCrlScopes')
			->willReturn([
				['instanceId' => 'instance-a', 'generation' => 1, 'engineType' => 'o'],
			]);

		$this->crlMapper->expects($this->once())
			->method('getRevokedCertificates')
			->with('instance-a', 1, 'o')
			->willReturn([]);

		$this->crlMapper->expects($this->once())
			->method('getLastCrlNumber')
			->with('instance-a', 1, 'openssl')
			->willReturn(0);

		$mockEngine = $this->createMock(IEngineHandler::class);
		$mockEngine->expects($this->once())
			->method('generateCrlDer')
			->with([], 'instance-a', 1, 1)
			->willReturnCallback(static function (): string {
				throw new \Error('boom');
			});

		$this->certificateEngineFactory->expects($this->once())
			->method('getEngine')
			->willReturn($mockEngine);

		$this->logger->expects($this->once())
			->method('error')
			->with('Failed to generate CRL', $this->callback(static fn (array $context): bool => $context['exception'] instanceof \Error));

		$this->logger->expects($this->never())
			->method('warning');

		$this->expectException(\Error::class);
		$this->expectExceptionMessage('boom');

		$this->service->refreshGeneratedCrlCache();
	}

	public function testGetRevokedCertificatesReturnsFormattedArray(): void {
		$entity1 = $this->createRevokedCertificateEntity(123456, 'user1@example.com', 1);
		$entity2 = $this->createRevokedCertificateEntity(789012, 'user2@example.com', 2);

		$this->crlMapper->expects($this->once())
			->method('getRevokedCertificates')
			->willReturn([$entity1, $entity2]);

		$result = $this->service->getRevokedCertificates();

		$this->assertIsArray($result);
		$this->assertCount(2, $result);

		// Test first certificate
		$this->assertArrayHasKey('serial_number', $result[0]);
		$this->assertArrayHasKey('owner', $result[0]);
		$this->assertArrayHasKey('reason_code', $result[0]);
		$this->assertEquals(123456, $result[0]['serial_number']);
		$this->assertEquals('user1@example.com', $result[0]['owner']);
		$this->assertEquals(1, $result[0]['reason_code']);
	}

	public function testGetStatisticsReturnsMapperData(): void {
		$expectedStats = [
			'total' => 100,
			'active' => 85,
			'revoked' => 10,
			'expired' => 5,
		];

		$this->crlMapper->expects($this->once())
			->method('getStatistics')
			->willReturn($expectedStats);

		$result = $this->service->getStatistics();

		$this->assertEquals($expectedStats, $result);
	}

	private function getCurrentRefreshDate(): string {
		return (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get())))
			->format('Y-m-d');
	}

	private function createRevokedCertificateEntity(int $serialNumber, string $owner, int $reasonCode): Crl {
		$crl = new Crl();
		$crl->setSerialNumber($serialNumber);
		$crl->setOwner($owner);
		$crl->setReasonCode($reasonCode);
		$crl->setRevokedBy('admin');
		$crl->setRevokedAt(new DateTime('2025-01-17 10:00:00'));
		$crl->setCrlNumber(1);

		return $crl;
	}
}
