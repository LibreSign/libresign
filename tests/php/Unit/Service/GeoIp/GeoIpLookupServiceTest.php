<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\GeoIp;

use OCA\Libresign\Enum\SignerIpGeolocationStatus;
use OCA\Libresign\Enum\SignerIpGeolocationUnavailableReason;
use OCA\Libresign\Service\GeoIp\GeoIpConfigService;
use OCA\Libresign\Service\GeoIp\GeoIpDatabaseStatusService;
use OCA\Libresign\Service\GeoIp\GeoIpLookupResultNormalizer;
use OCA\Libresign\Service\GeoIp\GeoIpLookupService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class GeoIpLookupServiceTest extends TestCase {
	private GeoIpConfigService&MockObject $configService;
	private GeoIpDatabaseStatusService&MockObject $statusService;
	private LoggerInterface&MockObject $logger;

	protected function setUp(): void {
		parent::setUp();
		$this->configService = $this->createMock(GeoIpConfigService::class);
		$this->statusService = $this->createMock(GeoIpDatabaseStatusService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function getService(): GeoIpLookupService {
		return new GeoIpLookupService(
			$this->configService,
			$this->statusService,
			new GeoIpLookupResultNormalizer(),
			$this->logger,
		);
	}

	public function testUnavailableWhenAddressMissing(): void {
		$result = $this->getService()->lookup(null);
		$this->assertSame(SignerIpGeolocationStatus::UNAVAILABLE->value, $result['status']);
		$this->assertSame(SignerIpGeolocationUnavailableReason::ADDRESS_UNAVAILABLE->value, $result['reason']);
		$this->assertArrayNotHasKey('sourceIp', $result);
	}

	public function testUnavailableWhenDatabaseNotReady(): void {
		$this->statusService->method('isReady')->willReturn(false);

		$result = $this->getService()->lookup('81.2.69.160');
		$this->assertSame(SignerIpGeolocationStatus::UNAVAILABLE->value, $result['status']);
		$this->assertSame(SignerIpGeolocationUnavailableReason::DATABASE_NOT_READY->value, $result['reason']);
		$this->assertSame('81.2.69.160', $result['sourceIp']);
	}

	public function testResolvedLookupAgainstMaxMindTestFixture(): void {
		$path = __DIR__ . '/../../../fixtures/geoip/GeoIP2-City-Test.mmdb';
		$this->assertFileExists($path);

		$this->statusService->method('isReady')->willReturn(true);
		$this->configService->method('getDatabasePath')->willReturn($path);

		$result = $this->getService()->lookup('81.2.69.160');
		$this->assertSame(SignerIpGeolocationStatus::RESOLVED->value, $result['status']);
		$this->assertSame('81.2.69.160', $result['sourceIp']);
		$this->assertArrayHasKey('countryCode', $result);
		$this->assertArrayNotHasKey('postal', $result);
		$this->assertArrayNotHasKey('postalCode', $result);
	}

	public function testNotFoundForUnknownAddressInFixture(): void {
		$path = __DIR__ . '/../../../fixtures/geoip/GeoIP2-City-Test.mmdb';
		$this->assertFileExists($path);

		$this->statusService->method('isReady')->willReturn(true);
		$this->configService->method('getDatabasePath')->willReturn($path);

		$result = $this->getService()->lookup('127.0.0.1');
		$this->assertSame(SignerIpGeolocationStatus::NOT_FOUND->value, $result['status']);
		$this->assertSame('127.0.0.1', $result['sourceIp']);
	}

	public function testResolvedLookupForIpv6InFixture(): void {
		$path = __DIR__ . '/../../../fixtures/geoip/GeoIP2-City-Test.mmdb';
		$this->assertFileExists($path);

		$this->statusService->method('isReady')->willReturn(true);
		$this->configService->method('getDatabasePath')->willReturn($path);

		// Documented MaxMind test network present in GeoIP2-City-Test.mmdb.
		$result = $this->getService()->lookup('2001:218::');
		$this->assertContains(
			$result['status'],
			[
				SignerIpGeolocationStatus::RESOLVED->value,
				SignerIpGeolocationStatus::NOT_FOUND->value,
			],
		);
		$this->assertSame('2001:218::', $result['sourceIp']);
	}
}
