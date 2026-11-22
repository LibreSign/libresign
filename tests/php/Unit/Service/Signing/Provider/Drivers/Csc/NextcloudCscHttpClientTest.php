<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing\Provider\Drivers\Csc;

use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\NextcloudCscHttpClient;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

final class NextcloudCscHttpClientTest extends TestCase {
	public function testUsesSsrfSafeNextcloudOptionsAndNeverRedirects(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getBody')->willReturn('{"specs":"1.0.4.0"}');
		$response->method('getStatusCode')->willReturn(200);
		$client = $this->createMock(IClient::class);
		$client->expects($this->once())->method('post')->with(
			'https://csc.example.org/csc/v1/info',
			$this->callback(static fn (array $opts): bool =>
				$opts['headers']['Authorization'] === 'Bearer synthetic-token'
				&& $opts['allow_redirects'] === false
				&& $opts['nextcloud']['allow_local_address'] === false
				&& $opts['http_errors'] === false
				&& $opts['verify'] === true
				&& $opts['body'] === '{}'),
		)->willReturn($response);
		$service = $this->createMock(IClientService::class);
		$service->method('newClient')->willReturn($client);
		$received = (new NextcloudCscHttpClient($service, $this->config()))->request(
			'POST', 'https://csc.example.org/csc/v1/info', ['Authorization: Bearer synthetic-token'], '{}',
		);
		$this->assertSame(200, $received->statusCode);
		$this->assertSame('{"specs":"1.0.4.0"}', $received->body);
	}

	public function testNeverExposesTransportErrorSecrets(): void {
		$client = $this->createMock(IClient::class);
		$client->method('post')->willThrowException(new \RuntimeException('leaked-secret'));
		$client->method('getResponseFromThrowable')->willThrowException(new \RuntimeException('leaked-secret'));
		$service = $this->createMock(IClientService::class);
		$service->method('newClient')->willReturn($client);
		$received = (new NextcloudCscHttpClient($service, $this->config()))->request('POST', 'https://csc.example.org', [], '{}');
		$this->assertSame(0, $received->statusCode);
		$this->assertSame('CSC transport failed.', $received->transportError);
		$this->assertStringNotContainsString('leaked-secret', $received->transportError);
	}

	public function testRefusesFollowingRedirectBeforeNetwork(): void {
		$service = $this->createMock(IClientService::class);
		$service->expects($this->never())->method('newClient');
		$this->expectException(\InvalidArgumentException::class);
		(new NextcloudCscHttpClient($service, $this->config()))->request('POST', 'https://csc.example.org', [], '', 10, true);
	}

	public function testDoesNotRetryHttp503(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(503);
		$response->method('getBody')->willReturn('unavailable');
		$client = $this->createMock(IClient::class);
		$client->expects($this->once())->method('post')->willReturn($response);
		$service = $this->createMock(IClientService::class);
		$service->method('newClient')->willReturn($client);
		$this->assertSame(503, (new NextcloudCscHttpClient($service, $this->config()))->request('POST', 'https://csc.example.org')->statusCode);
	}

	private function config(): IConfig {
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueBool')->willReturnCallback(static fn (string $key, bool $default = false): bool => $default);
		return $config;
	}
}
