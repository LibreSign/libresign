<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Db;

use DateTime;
use OCA\Libresign\Db\Crl;
use OCA\Libresign\Enum\CRLReason;
use OCA\Libresign\Enum\CRLStatus;
use PHPUnit\Framework\TestCase;

class CrlMapperTest extends TestCase {
	public function testEntityBasicFunctionality(): void {
		$certificate = new Crl();
		$certificate->setSerialNumber(123456);
		$certificate->setOwner('test-owner');
		$certificate->setStatus(CRLStatus::ISSUED);
		$certificate->setIssuedAt(new DateTime());

		$this->assertEquals(123456, $certificate->getSerialNumber());
		$this->assertEquals('test-owner', $certificate->getOwner());
		$this->assertEquals(CRLStatus::ISSUED->value, $certificate->getStatus());
		$this->assertFalse($certificate->isRevoked());
		$this->assertTrue($certificate->isValid());
	}

	public function testCrlRevocation(): void {
		$certificate = new Crl();
		$certificate->setStatus(CRLStatus::REVOKED);
		$certificate->setReasonCode(CRLReason::KEY_COMPROMISE->value);
		$certificate->setRevokedAt(new DateTime());

		$this->assertTrue($certificate->isRevoked());
		$this->assertFalse($certificate->isValid());
		$this->assertEquals(CRLReason::KEY_COMPROMISE->value, $certificate->getReasonCode());
	}

	public function testCrlExpiration(): void {
		$certificate = new Crl();
		$certificate->setStatus(CRLStatus::ISSUED);
		$certificate->setValidTo(new DateTime('-1 day')); // Expired yesterday

		$this->assertFalse($certificate->isRevoked());
		$this->assertTrue($certificate->isExpired());
		$this->assertFalse($certificate->isValid());
	}

	/**
	 * Verifies the entity-level properties that the getRevokedCertificates()
	 * expiry filter relies upon: a revoked-but-expired certificate has both
	 * isRevoked() and isExpired() truthy. The SQL filter (valid_to IS NULL OR
	 * valid_to >= NOW) is the DB-side enforcement; this test documents the
	 * domain invariant at the entity level.
	 */
	public function testRevokedExpiredCertificateHasBothFlags(): void {
		$certificate = new Crl();
		$certificate->setStatus(CRLStatus::REVOKED);
		$certificate->setValidTo(new DateTime('-1 day'));
		$certificate->setRevokedAt(new DateTime('-2 days'));

		$this->assertTrue($certificate->isRevoked(), 'Certificate should still be marked revoked');
		$this->assertTrue($certificate->isExpired(), 'Certificate should be expired');
	}

	/**
	 * A revoked certificate without an expiry date (valid_to IS NULL) must
	 * always appear in CRL output — the SQL filter passes NULL rows through.
	 */
	public function testRevokedCertificateWithNullValidToIsNotExpired(): void {
		$certificate = new Crl();
		$certificate->setStatus(CRLStatus::REVOKED);
		$certificate->setValidTo(null);

		$this->assertTrue($certificate->isRevoked());
		$this->assertFalse($certificate->isExpired(), 'No valid_to means never expires');
	}
}
