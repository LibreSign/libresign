<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\GeoIp;

use OCA\Libresign\Enum\GeoIpDatabaseStatus;
use OCA\Libresign\Service\GeoIp\GeoIpConfigService;
use OCA\Libresign\Service\GeoIp\GeoIpDatabaseStatusService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class GeoIpDatabaseStatusServiceTest extends TestCase {
	private GeoIpConfigService&MockObject $configService;
	private LoggerInterface&MockObject $logger;

	protected function setUp(): void {
		parent::setUp();
		$this->configService = $this->createMock(GeoIpConfigService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function getService(): GeoIpDatabaseStatusService {
		return new GeoIpDatabaseStatusService($this->configService, $this->logger);
	}

	public function testNotConfiguredWhenPathMissing(): void {
		$this->configService->method('getDatabasePath')->willReturn(null);

		$this->assertSame([
			'path' => null,
			'status' => GeoIpDatabaseStatus::NOT_CONFIGURED->value,
		], $this->getService()->getStatus());
	}

	public function testNotFoundWhenFileMissing(): void {
		$this->configService->method('getDatabasePath')->willReturn('/tmp/missing-geoip.mmdb');

		$status = $this->getService()->getStatus();
		$this->assertSame('/tmp/missing-geoip.mmdb', $status['path']);
		$this->assertSame(GeoIpDatabaseStatus::NOT_FOUND->value, $status['status']);
	}

	public function testReadyWithMaxMindTestFixture(): void {
		$path = __DIR__ . '/../../../fixtures/geoip/GeoIP2-City-Test.mmdb';
		$this->assertFileExists($path);
		$this->configService->method('getDatabasePath')->willReturn($path);

		$status = $this->getService()->getStatus();
		$this->assertSame(GeoIpDatabaseStatus::READY->value, $status['status']);
		$this->assertSame($path, $status['path']);
		$this->assertSame('GeoIP2-City', $status['databaseType'] ?? null);
		$this->assertArrayHasKey('buildEpoch', $status);
		$this->assertArrayHasKey('modifiedAt', $status);
		$this->assertTrue($this->getService()->isReady());
	}

	public function testInvalidDatabaseWhenFileIsNotMmdb(): void {
		$path = tempnam(sys_get_temp_dir(), 'geoip-invalid-');
		$this->assertNotFalse($path);
		file_put_contents($path, 'not-an-mmdb');
		try {
			$this->configService->method('getDatabasePath')->willReturn($path);
			$this->logger->expects($this->once())->method('warning');

			$status = $this->getService()->getStatus();
			$this->assertSame(GeoIpDatabaseStatus::INVALID_DATABASE->value, $status['status']);
			$this->assertSame($path, $status['path']);
		} finally {
			@unlink($path);
		}
	}

	public function testUnsupportedDatabaseTypeIsDetected(): void {
		$service = $this->getService();
		$this->assertFalse($service->isSupportedDatabaseType('GeoIP2-Country'));
		$this->assertTrue($service->isSupportedDatabaseType('GeoLite2-City'));
	}
}
