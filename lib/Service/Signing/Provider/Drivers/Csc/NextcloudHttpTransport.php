<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\Provider\Drivers\Csc;

use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Infrastructure\Native\Contract\HttpClientInterface;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Infrastructure\Native\ValueObject\HttpResponse;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;

/**
 * Thin adaptation of Nextcloud's SSRF-aware HTTP client to SignerPHP's
 * existing HTTP contract. No CSC serialization, retry or redirect logic.
 */
final class NextcloudHttpTransport implements HttpClientInterface {
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
		$this->assertSafeRequest($method, $timeoutSeconds, $followRedirects);
		$client = $this->clientService->newClient();
		$response = $this->send($client, $url, $body, $timeoutSeconds, self::parseHeaders($headers));

		return $this->toResponse($response);
	}

	private function assertSafeRequest(string $method, int $timeoutSeconds, bool $followRedirects): void {
		if (strtoupper($method) !== 'POST' || $followRedirects || $timeoutSeconds < 1) {
			throw new \InvalidArgumentException('CSC transport only supports non-redirecting POST requests.');
		}
		// The global settings can override request-level SSRF restrictions.
		if ($this->systemConfig->getSystemValueBool('allow_local_remote_servers', false)
			|| !$this->systemConfig->getSystemValueBool('dns_pinning', true)) {
			throw new \LogicException('CSC transport requires local-address blocking and DNS pinning.');
		}
	}

	/** @param array<int, string> $headers
	 * @return array<string, string>
	 */
	private static function parseHeaders(array $headers): array {
		$parsed = [];
		foreach ($headers as $header) {
			if (!is_string($header) || !str_contains($header, ':') || preg_match('/[\r\n]/', $header)) {
				throw new \InvalidArgumentException('Invalid CSC HTTP header.');
			}
			[$name, $value] = explode(':', $header, 2);
			if (!preg_match('/^[A-Za-z0-9-]+$/D', $name)) {
				throw new \InvalidArgumentException('Invalid CSC HTTP header name.');
			}
			$parsed[$name] = ltrim($value);
		}
		return $parsed;
	}

	/** @param array<string, string> $headers */
	private function send(IClient $client, string $url, string $body, int $timeoutSeconds, array $headers): ?IResponse {
		try {
			return $client->post($url, [
				'headers' => $headers,
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
				return $client->getResponseFromThrowable($e);
			} catch (\Throwable) {
				// No raw transport diagnostics may escape with credentials.
				return null;
			}
		}
	}

	private function toResponse(?IResponse $response): HttpResponse {
		if ($response === null) {
			return new HttpResponse(0, '', 'CSC transport failed.');
		}
		try {
			$statusCode = $response->getStatusCode();
			$body = $response->getBody();
		} catch (\Throwable) {
			return new HttpResponse(0, '', 'CSC transport failed.');
		}
		if (!is_string($body)) {
			return new HttpResponse($statusCode, '', 'CSC response body is not a string.');
		}
		return new HttpResponse($statusCode, $body);
	}
}
