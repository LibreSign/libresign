<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\Provider\Drivers\Csc;

use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;

/**
 * Read-only CSC connectivity check. It never obtains or accepts an OAuth
 * token, authorizes a certificate or submits a signature.
 *
 * Verify the configured endpoint using the same SSRF/DNS safeguards as the
 * signing adapter; a reachable service is not proof of personal signing.
 */
final class CscProviderProbe {
	public function __construct(
		private IClientService $clients,
		private IConfig $config,
	) {
	}

	/**
	 * @return array{reachable:bool,serviceName:string,apiVersion:string,protocol:string,protocolCompatible:bool,personalSigningReady:bool,result:string}
	 */
	public function inspect(string $baseUrl): array {
		// Deliberately construct through the normal adapter to reuse its
		// public HTTPS endpoint, Nextcloud SSRF and DNS-pinning assertions.
		CscIntegration::fromConfiguredInstance($this->clients, $this->config, ['baseUrl' => $baseUrl]);

		$client = $this->clients->newClient();
		$prefix = rtrim($baseUrl, '/');
		[$status, $body] = $this->readInfo($client, $prefix . '/csc/v1/info');
		$route = 'v1';
		if ($status === 404) {
			[$status, $body] = $this->readInfo($client, $prefix . '/csc/v2/info');
			$route = 'v2';
		}
		if ($status === 401 || $status === 403) {
			return $this->result(true, '', '', '', false, 'authentication_required');
		}
		if ($status === 0) {
			return $this->result(false, '', '', '', false, 'unreachable');
		}
		if ($status !== 200 || $body === null) {
			return $this->result(false, '', '', '', false, 'invalid_response');
		}
		try {
			$info = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return $this->result(true, '', '', '', false, 'invalid_response');
		}
		if (!is_array($info) || array_is_list($info)) {
			return $this->result(true, '', '', '', false, 'invalid_response');
		}
		$version = $info['specs'] ?? null;
		$name = $info['name'] ?? null;
		$methods = $info['methods'] ?? null;
		if (!is_string($version) || strlen($version) > 24
			|| preg_match('/^[0-9]+(?:\.[0-9]+){3}$/D', $version) !== 1
			|| !is_string($name) || strlen($name) > 255
			|| !is_array($methods) || !array_is_list($methods) || count($methods) > 64
			|| count(array_filter($methods, static fn (mixed $m): bool => is_string($m) && strlen($m) <= 96)) !== count($methods)) {
			return $this->result(true, '', '', '', false, 'invalid_response');
		}
		$serviceName = trim((string)preg_replace('/[\x00-\x1f\x7f]/', '', $name));
		$compatible = $route === 'v1' && $version === '1.0.4.0'
			&& in_array('credentials/list', $methods, true)
			&& in_array('credentials/info', $methods, true)
			&& in_array('signatures/signHash', $methods, true);
		return $this->result(true, mb_substr($serviceName, 0, 128), $version, $route,
			$compatible, $compatible ? 'signer_oauth_not_configured' : 'unsupported_protocol');
	}

	/** @return array{int,?string} */
	private function readInfo(IClient $client, string $url): array {
		try {
			$response = $client->post($url, [
				'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
				'body' => '{}',
				'timeout' => 8,
				'connect_timeout' => 4,
				'allow_redirects' => false,
				'http_errors' => false,
				'verify' => true,
				'nextcloud' => ['allow_local_address' => false],
			]);
			$body = $response->getBody();
			if (!is_string($body) || strlen($body) > 16384) {
				return [$response->getStatusCode(), null];
			}
			return [$response->getStatusCode(), $body];
		} catch (\Exception) {
			// Provider-origin diagnostics may contain OAuth credentials.
			return [0, null];
		}
	}

	/**
	 * @return array{reachable:bool,serviceName:string,apiVersion:string,protocol:string,protocolCompatible:bool,personalSigningReady:bool,result:string}
	 */
	private function result(bool $reachable, string $serviceName, string $apiVersion, string $protocol,
		bool $compatible, string $reason): array {
		return [
			'reachable' => $reachable,
			'serviceName' => $serviceName,
			'apiVersion' => $apiVersion,
			'protocol' => $protocol,
			'protocolCompatible' => $compatible,
			'personalSigningReady' => false,
			'result' => $reason,
		];
	}
}
