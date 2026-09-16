<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignerIpGeolocation;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Service\GeoIp\GeoIpLookupService;
use OCA\Libresign\Service\Policy\Provider\SignerIpGeolocation\SignerIpGeolocationPolicy;
use OCA\Libresign\Service\SignerIpGeolocation\SignerIpGeolocationPolicyService;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignerIpGeolocationPolicyServiceTest extends TestCase {
	private FileMapper&MockObject $fileMapper;
	private GeoIpLookupService&MockObject $lookupService;

	protected function setUp(): void {
		parent::setUp();
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->lookupService = $this->createMock(GeoIpLookupService::class);
	}

	private function getService(): SignerIpGeolocationPolicyService {
		return new SignerIpGeolocationPolicyService($this->fileMapper, $this->lookupService);
	}

	public function testAbsentSnapshotMeansDisabled(): void {
		$file = new File();
		$this->assertFalse($this->getService()->isEnabled($file));
		$this->assertSame(['mode' => 'disabled'], $this->getService()->getPolicyValue($file));
	}

	public function testCollectMetadataSkippedWhenDisabled(): void {
		$file = new File();
		$request = $this->createMock(IRequest::class);
		$this->lookupService->expects($this->never())->method('lookup');

		$this->assertNull($this->getService()->collectMetadata($file, $request));
	}

	public function testCollectMetadataUsesFrozenEnabledSnapshot(): void {
		$file = new File();
		$file->setMetadata([
			'policy_snapshot' => [
				SignerIpGeolocationPolicy::KEY => [
					'effectiveValue' => ['mode' => 'enabled'],
					'sourceScope' => 'system',
				],
			],
		]);

		$request = $this->createMock(IRequest::class);
		$request->method('getRemoteAddress')->willReturn('81.2.69.160');
		$this->lookupService
			->expects($this->once())
			->method('lookup')
			->with('81.2.69.160')
			->willReturn(['status' => 'resolved', 'sourceIp' => '81.2.69.160', 'countryCode' => 'GB']);

		$result = $this->getService()->collectMetadata($file, $request);
		$this->assertSame('resolved', $result['status'] ?? null);
	}

	public function testMergeIntoMetadataPreservesDevice(): void {
		$merged = $this->getService()->mergeIntoMetadata(
			[
				'geolocation' => [
					'device' => ['status' => 'collected', 'latitude' => 1.0, 'longitude' => 2.0],
				],
			],
			['status' => 'resolved', 'sourceIp' => '1.2.3.4'],
		);

		$this->assertSame('collected', $merged['geolocation']['device']['status']);
		$this->assertSame('resolved', $merged['geolocation']['ip']['status']);
	}
}
