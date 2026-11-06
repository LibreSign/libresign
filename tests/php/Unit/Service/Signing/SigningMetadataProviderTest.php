<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\RequestMetadataService;
use OCA\Libresign\Service\SignerGeolocation\SignerGeolocationMetadataValidator;
use OCA\Libresign\Service\SignerIpGeolocation\SignerIpGeolocationPolicyService;
use OCA\Libresign\Service\Signing\SigningMetadataProvider;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SigningMetadataProviderTest extends TestCase {
	#[DataProvider('locationScenarios')]
	public function testCollectsRequestAndAllowedLocationMetadata(array $submittedDevice, ?array $normalizedDevice, ?array $ipLocation, array $expected): void {
		$file = new File();
		$signRequest = new SignRequest();
		$request = $this->createMock(IRequest::class);
		$requestMetadata = $this->createMock(RequestMetadataService::class);
		$requestMetadata->method('collectMetadata')->willReturn(['user-agent' => 'integration-test']);
		$device = $this->createMock(SignerGeolocationMetadataValidator::class);
		$device->expects(self::once())->method('normalize')->with($submittedDevice ?: null)->willReturn($normalizedDevice);
		$device->expects(self::once())->method('validateSubmission')->with($signRequest, $normalizedDevice);
		$device->method('mergeDeviceIntoMetadata')->willReturnCallback(
			static fn (array $metadata, array $location): array => [...$metadata, 'device' => $location],
		);
		$ip = $this->createMock(SignerIpGeolocationPolicyService::class);
		$ip->expects(self::once())->method('collectMetadata')->with($file, $request)->willReturn($ipLocation);
		$ip->method('mergeIntoMetadata')->willReturnCallback(
			static fn (array $metadata, array $location): array => [...$metadata, 'ip' => $location],
		);

		$provider = new SigningMetadataProvider($request, $requestMetadata, $device, $ip);

		self::assertSame($expected, $provider->collect($file, $signRequest, $submittedDevice));
	}

	public static function locationScenarios(): iterable {
		$device = ['status' => 'collected', 'latitude' => -22.9];
		$ip = ['country' => 'BR'];
		yield 'no geolocation' => [[], null, null, ['user-agent' => 'integration-test']];
		yield 'device only' => [$device, $device, null, ['user-agent' => 'integration-test', 'device' => $device]];
		yield 'IP only' => [[], null, $ip, ['user-agent' => 'integration-test', 'ip' => $ip]];
		yield 'both sources' => [$device, $device, $ip, ['user-agent' => 'integration-test', 'device' => $device, 'ip' => $ip]];
	}

	public function testInvalidDeviceLocationPreventsIpCollection(): void {
		$signRequest = new SignRequest();
		$device = $this->createMock(SignerGeolocationMetadataValidator::class);
		$device->method('normalize')->willReturn(['status' => 'collected']);
		$device->method('validateSubmission')->with($signRequest)->willThrowException(new \RuntimeException('Geolocation required'));
		$ip = $this->createMock(SignerIpGeolocationPolicyService::class);
		$ip->expects(self::never())->method('collectMetadata');
		$provider = new SigningMetadataProvider(
			$this->createMock(IRequest::class),
			$this->createMock(RequestMetadataService::class),
			$device,
			$ip,
		);

		$this->expectExceptionMessage('Geolocation required');
		$provider->collect(new File(), $signRequest);
	}
}
