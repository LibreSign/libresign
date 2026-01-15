<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\RequestMetadataService;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RequestMetadataServiceTest extends TestCase {
	private RequestMetadataService $service;
	private IRequest&MockObject $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->service = new RequestMetadataService($this->request);
	}

	public static function metadataCollectionProvider(): array {
		return [
			'full metadata with user agent and IP' => [
				'userAgent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
				'remoteAddress' => '192.168.1.100',
				'expectedUserAgent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
				'expectedRemoteAddress' => '192.168.1.100',
			],
			'missing user agent' => [
				'userAgent' => '',
				'remoteAddress' => '10.0.0.5',
				'expectedUserAgent' => '',
				'expectedRemoteAddress' => '10.0.0.5',
			],
			'missing remote address' => [
				'userAgent' => 'curl/7.68.0',
				'remoteAddress' => '',
				'expectedUserAgent' => 'curl/7.68.0',
				'expectedRemoteAddress' => '',
			],
			'both empty' => [
				'userAgent' => '',
				'remoteAddress' => '',
				'expectedUserAgent' => '',
				'expectedRemoteAddress' => '',
			],
			'IPv6 address' => [
				'userAgent' => 'Firefox/95.0',
				'remoteAddress' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
				'expectedUserAgent' => 'Firefox/95.0',
				'expectedRemoteAddress' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
			],
			'localhost IPv4' => [
				'userAgent' => 'PostmanRuntime/7.28.4',
				'remoteAddress' => '127.0.0.1',
				'expectedUserAgent' => 'PostmanRuntime/7.28.4',
				'expectedRemoteAddress' => '127.0.0.1',
			],
			'localhost IPv6' => [
				'userAgent' => 'Wget/1.20.3',
				'remoteAddress' => '::1',
				'expectedUserAgent' => 'Wget/1.20.3',
				'expectedRemoteAddress' => '::1',
			],
			'mobile user agent' => [
				'userAgent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X)',
				'remoteAddress' => '203.0.113.42',
				'expectedUserAgent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X)',
				'expectedRemoteAddress' => '203.0.113.42',
			],
		];
	}

	#[DataProvider('metadataCollectionProvider')]
	public function testCollectMetadata(
		?string $userAgent,
		?string $remoteAddress,
		?string $expectedUserAgent,
		?string $expectedRemoteAddress,
	): void {
		$this->request->expects($this->once())
			->method('getHeader')
			->with('User-Agent')
			->willReturn($userAgent ?? '');

		$this->request->expects($this->once())
			->method('getRemoteAddress')
			->willReturn($remoteAddress ?? '');

		$metadata = $this->service->collectMetadata();

		$this->assertIsArray($metadata);
		$this->assertArrayHasKey('user-agent', $metadata);
		$this->assertArrayHasKey('remote-address', $metadata);
		$this->assertSame($expectedUserAgent ?? '', $metadata['user-agent']);
		$this->assertSame($expectedRemoteAddress ?? '', $metadata['remote-address']);
	}

	public function testMetadataStructure(): void {
		$this->request->method('getHeader')->willReturn('TestAgent/1.0');
		$this->request->method('getRemoteAddress')->willReturn('192.168.1.1');

		$metadata = $this->service->collectMetadata();

		$this->assertCount(2, $metadata);
		$this->assertArrayNotHasKey('timestamp', $metadata);
		$this->assertArrayNotHasKey('request-id', $metadata);
	}
}
