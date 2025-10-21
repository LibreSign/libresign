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



	public function testRevokeCertificateWithValidReasonCode(): void {
		$serialNumber = 123456;
		$reasonCode = 1; // keyCompromise
		$reasonText = 'Certificate compromised';
		$revokedBy = 'admin';

		// Mock the dependencies for successful revocation
		$this->crlMapper->expects($this->once())
			->method('getNextCrlNumber')
			->willReturn(5);

		$this->crlMapper->expects($this->once())
			->method('revokeCertificate')
			->with(
				123456,
				CRLReason::KEY_COMPROMISE,
				$reasonText,
				$revokedBy,
				null,
				5
			);

		$result = $this->service->revokeCertificate($serialNumber, $reasonCode, $reasonText, $revokedBy);

		$this->assertTrue($result);
	}

	public function testRevokeCertificateWithInvalidReasonCode(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid CRLReason code: 99');

		$this->service->revokeCertificate(123456, 99, 'Test', 'admin');
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
			->with($revokedCertificates)
			->willReturn($mockCrlDer);

		$this->certificateEngineFactory->expects($this->once())
			->method('getEngine')
			->willReturn($mockEngine);

		$result = $this->service->generateCrlDer();

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
