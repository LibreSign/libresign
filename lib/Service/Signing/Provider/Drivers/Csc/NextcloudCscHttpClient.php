<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\Provider\Drivers\Csc;

use OCP\Http\Client\IClientService;
use OCP\IConfig;
use SignerPHP\PdfSigner\Infrastructure\Native\Contract\HttpClientInterface;
use SignerPHP\PdfSigner\Infrastructure\Native\ValueObject\HttpResponse;

/**
 * Thin adaptation of Nextcloud's SSRF-aware HTTP client to SignerPHP's
 * existing HTTP contract. No CSC serialization, retry or redirect logic.
 */
final class NextcloudCscHttpClient implements HttpClientInterface {
	public function __construct(
		private readonly IClientService $clientService,
		private readonly IConfig $systemConfig,
	) {
	}

	/** @param array<int, string> $headers */
	#[\Override]
	public function request(
		string $method,
		string $url,
		array $headers = [],
		string $body = '',
		int $timeoutSeconds = 10,
		bool $followRedirects = false,
	): HttpResponse {
		if (strtoupper($method) !== 'POST' || $followRedirects || $timeoutSeconds < 1) {
			throw new \InvalidArgumentException('CSC transport only supports non-redirecting POST requests.');
		}
		// Recheck for each request: a global Nextcloud configuration change
		// must not silently disable local-host/DNS-rebinding protection.
		if ($this->systemConfig->getSystemValueBool('allow_local_remote_servers', false)
			|| !$this->systemConfig->getSystemValueBool('dns_pinning', true)) {
			throw new \LogicException('CSC transport requires local-address blocking and DNS pinning.');
		}
		$httpHeaders = [];
		foreach ($headers as $header) {
			if (!is_string($header) || !str_contains($header, ':')
				|| preg_match('/[\r\n]/', $header)) {
				throw new \InvalidArgumentException('Invalid CSC HTTP header.');
			}
			[$name, $value] = explode(':', $header, 2);
			if (!preg_match('/^[A-Za-z0-9-]+$/', $name)) {
				throw new \InvalidArgumentException('Invalid CSC HTTP header name.');
			}
			$httpHeaders[$name] = ltrim($value);
		}
		$client = $this->clientService->newClient();
		try {
			$response = $client->post($url, [
				'headers' => $httpHeaders,
				'body' => $body,
				'timeout' => $timeoutSeconds,
				'connect_timeout' => $timeoutSeconds,
				'allow_redirects' => false,
				'http_errors' => false,
				'verify' => true,
				'nextcloud' => ['allow_local_address' => false],
			]);
		} catch (\Throwable $e) {
			try {
				$response = $client->getResponseFromThrowable($e);
			} catch (\Throwable) {
				// Never propagate URLs, authorization headers or token-bearing
				// exception messages from a transport implementation.
				return new HttpResponse(0, '', 'CSC transport failed.');
			}
		}
		$responseBody = $response->getBody();
		if (!is_string($responseBody)) {
			return new HttpResponse($response->getStatusCode(), '', 'CSC response body is not a string.');
		}

		return new HttpResponse($response->getStatusCode(), $responseBody);
	}
}
