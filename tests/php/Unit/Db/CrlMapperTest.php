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
}
