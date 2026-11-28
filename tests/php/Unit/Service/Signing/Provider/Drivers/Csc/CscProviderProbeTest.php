<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing\Provider\Drivers\Csc;

use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscProviderProbe;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

final class CscProviderProbeTest extends TestCase {
	private function config(bool $blockLocal = true, bool $pinDns = true): IConfig {
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueBool')->willReturnCallback(
			static fn (string $key, bool $default = false): bool => match ($key) {
				'allow_local_remote_servers' => !$blockLocal,
				'dns_pinning' => $pinDns,
				default => $default,
			},
		);
		return $config;
	}

	private function response(int $status, string $body): IResponse {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn($status);
		$response->method('getBody')->willReturn($body);
		return $response;
	}

	public function testRealCscV1MetadataIsRecognizedWithoutEnablingPersonalSigning(): void {
		$client = $this->createMock(IClient::class);
		$client->expects($this->once())->method('post')->with(
			'https://provider.example.com/csc/v1/info',
			$this->callback(static fn (array $o): bool => ($o['allow_redirects'] ?? true) === false
				&& ($o['http_errors'] ?? true) === false
				&& ($o['nextcloud']['allow_local_address'] ?? true) === false),
		)->willReturn($this->response(200, json_encode([
			'specs' => '1.0.4.0', 'name' => 'Remote signer',
			'methods' => ['credentials/list', 'credentials/info', 'signatures/signHash', 'oauth2/authorize'],
		], JSON_THROW_ON_ERROR)));
		$clients = $this->createMock(IClientService::class);
		$clients->method('newClient')->willReturn($client);
		$result = (new CscProviderProbe($clients, $this->config()))->inspect('https://provider.example.com');
		self::assertTrue($result['reachable']);
		self::assertTrue($result['protocolCompatible']);
		self::assertFalse($result['personalSigningReady']);
		self::assertSame('1.0.4.0', $result['apiVersion']);
		self::assertSame('signer_oauth_not_configured', $result['result']);
	}

	public function testCscV2EndpointIsDetectedButNotClaimedSupported(): void {
		$client = $this->createMock(IClient::class);
		$urls = [];
		$client->method('post')->willReturnCallback(function (string $url) use (&$urls): IResponse {
			$urls[] = $url;
			return str_ends_with($url, '/v1/info')
				? $this->response(404, '{}')
				: $this->response(200, json_encode([
					'specs' => '2.2.0.0', 'name' => 'CSC 2.2 service',
					'methods' => ['credentials/list', 'credentials/info', 'signatures/signHash'],
				], JSON_THROW_ON_ERROR));
		});
		$clients = $this->createMock(IClientService::class);
		$clients->method('newClient')->willReturn($client);
		$result = (new CscProviderProbe($clients, $this->config()))->inspect('https://provider.example.com');
		self::assertSame(['https://provider.example.com/csc/v1/info', 'https://provider.example.com/csc/v2/info'], $urls);
		self::assertTrue($result['reachable']);
		self::assertFalse($result['protocolCompatible']);
		self::assertFalse($result['personalSigningReady']);
		self::assertSame('unsupported_protocol', $result['result']);
	}

	public function testLocalAddressIsRejectedBeforeCreatingHttpClient(): void {
		$clients = $this->createMock(IClientService::class);
		$clients->expects($this->never())->method('newClient');
		$this->expectException(\InvalidArgumentException::class);
		(new CscProviderProbe($clients, $this->config()))->inspect('http://127.0.0.1:8080');
	}

	public function testDisabledDnsPinningPreventsTheProbe(): void {
		$clients = $this->createMock(IClientService::class);
		$clients->expects($this->never())->method('newClient');
		$this->expectException(\LogicException::class);
		(new CscProviderProbe($clients, $this->config(pinDns: false)))->inspect('https://provider.example.com');
	}

	public function testOAuthProtectedServiceDoesNotLeakProviderResponse(): void {
		$client = $this->createMock(IClient::class);
		$client->method('post')->willReturn($this->response(401, 'Bearer private-token'));
		$clients = $this->createMock(IClientService::class);
		$clients->method('newClient')->willReturn($client);
		$result = (new CscProviderProbe($clients, $this->config()))->inspect('https://provider.example.com');
		self::assertSame('authentication_required', $result['result']);
		self::assertStringNotContainsString('private-token', json_encode($result, JSON_THROW_ON_ERROR));
	}
}
