<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use DateTime;
use OCA\Libresign\Db\CrlMapper;
use OCA\Libresign\Service\SerialNumberService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SerialNumberServiceTest extends TestCase {
	private CrlMapper&MockObject $crlMapper;
	private SerialNumberService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->crlMapper = $this->createMock(CrlMapper::class);
		$this->service = new SerialNumberService($this->crlMapper);
	}

	public function testGenerateUniqueSerialPersistsCrlMetadata(): void {
		$expiresAt = new DateTime('2030-01-01 00:00:00');

		$this->crlMapper->expects($this->once())
			->method('createCertificate')
			->with(
				$this->isType('string'),
				'test-owner',
				'openssl',
				'inst1234567',
				4,
				$this->isInstanceOf(DateTime::class),
				$expiresAt,
				null,
				null,
				'leaf'
			);

		$serial = $this->service->generateUniqueSerial(
			'test-owner',
			'inst1234567',
			4,
			$expiresAt,
			'openssl',
		);

		$this->assertNotSame('', $serial);
		$this->assertSame(1, preg_match('/^[0-9]+$/', $serial));
	}
}
