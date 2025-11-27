<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use DateTime;
use OCA\Libresign\Db\Crl;
use OCA\Libresign\Db\CrlMapper;
use OCA\Libresign\Enum\CRLReason;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Service\CrlService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CrlServiceTest extends TestCase {
	private CrlService $service;
	private CrlMapper&MockObject $crlMapper;
	private LoggerInterface&MockObject $logger;
	private CertificateEngineFactory&MockObject $certificateEngineFactory;

	protected function setUp(): void {
		$this->crlMapper = $this->createMock(CrlMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);

		$this->service = new CrlService($this->crlMapper, $this->logger, $this->certificateEngineFactory);
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
				->method('revokeCertificate')
				->willReturnCallback(function ($serialNumber) use (&$callCount, $failOnIndex) {
					$callCount++;
					if ($failOnIndex !== null && $callCount === $failOnIndex) {
						throw new \Exception('Database error');
					}

					$cert = new Crl();
					$cert->setSerialNumber($serialNumber);
					return $cert;
				});
		}

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
			->method('revokeCertificate')
			->with(
				'123456',
				CRLReason::KEY_COMPROMISE,
				$reasonText,
				$revokedBy,
				null,
				5
			);

		$result = $this->service->revokeCertificate($serialNumber, $reason, $reasonText, $revokedBy);

		$this->assertTrue($result);
	}

	public function testGenerateCrlDerReturnsValidBinaryData(): void {
		// Create revoked certificates data
		$revokedCertificates = [
			$this->createRevokedCertificateEntity(123456, 'user1@example.com', 1),
			$this->createRevokedCertificateEntity(789012, 'user2@example.com', 2),
		];

		$this->crlMapper->expects($this->once())
			->method('getRevokedCertificates')
			->willReturn($revokedCertificates);

		// Mock the certificate engine
		$mockEngine = $this->createMock(IEngineHandler::class);
		$mockCrlDer = "\x30\x82\x01\x00"; // Valid DER sequence
		$mockEngine->expects($this->once())
			->method('generateCrlDer')
			->with($revokedCertificates, 'test-instance', 1, 1)
			->willReturn($mockCrlDer);

		$this->certificateEngineFactory->expects($this->once())
			->method('getEngine')
			->willReturn($mockEngine);

		// Mock the getLastCrlNumber method
		$this->crlMapper->expects($this->once())
			->method('getLastCrlNumber')
			->with('test-instance', 1, 'openssl')
			->willReturn(0);

		$result = $this->service->generateCrlDer('test-instance', 1, 'openssl');

		$this->assertIsString($result);
		$this->assertNotEmpty($result);
		// Basic DER structure should start with SEQUENCE tag (0x30)
		$this->assertEquals(0x30, ord($result[0]));
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

	private function createRevokedCertificateEntity(int $serialNumber, string $owner, int $reasonCode): Crl {
		// Create a real Crl entity and set its properties
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
