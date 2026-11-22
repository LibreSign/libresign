<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\Provider\Drivers\Csc;

use OCP\Http\Client\IClientService;
use OCP\IConfig;
use SignerPHP\PdfSigner\Application\DTO\ExternalSigningPayload;
use SignerPHP\PdfSigner\Application\DTO\SignatureValue;
use SignerPHP\PdfSigner\Infrastructure\Native\Contract\HttpClientInterface;
use SignerPHP\PdfSigner\Infrastructure\Remote\Csc\CscCertificateDiscovery;
use SignerPHP\PdfSigner\Infrastructure\Remote\Csc\CscClient;
use SignerPHP\PdfSigner\Infrastructure\Remote\Csc\CscSignatureProvider;
use SignerPHP\PdfSigner\RemoteSigning\Contract\RemoteCertificateDiscoveryInterface;
use SignerPHP\PdfSigner\RemoteSigning\Contract\RemoteSignatureProviderInterface;
use SignerPHP\PdfSigner\RemoteSigning\DTO\RemoteCertificate;

/**
 * A transient CSC adapter: integration-instance persistence belongs to #9596;
 * signer authorization, session ownership and idempotency belong to #9603.
 *
 * A configured instance may discover public credentials, but cannot sign
 * until a signer-bound authorization contract is implemented. Only the
 * loopback synthetic laboratory can invoke the private-key operation here.
 */
final class CscIntegration implements RemoteCertificateDiscoveryInterface, RemoteSignatureProviderInterface {
	private bool $signAttempted = false;

	private function __construct(
		private readonly CscClient $client,
		private readonly bool $syntheticLab,
	) {
	}

	/**
	 * @param array{baseUrl: string} $configuration Validated, non-secret instance configuration.
	 * @param ?string $accessToken Decrypted just in time by the eventual #9596 secret store.
	 */
	public static function fromConfiguredInstance(
		IClientService $clientService,
		IConfig $systemConfig,
		array $configuration,
		#[\SensitiveParameter] ?string $accessToken = null,
	): self {
		// Nextcloud's global allow_local_remote_servers overrides per-request
		// restrictions. Its optional DNS pinning protects against rebinding.
		if ($systemConfig->getSystemValueBool('allow_local_remote_servers', false)
			|| !$systemConfig->getSystemValueBool('dns_pinning', true)) {
			throw new \LogicException('CSC requires Nextcloud local-address blocking and DNS pinning.');
		}
		$baseUrl = self::validatedEndpoint($configuration, false);
		if ($accessToken !== null && ($accessToken === '' || strlen($accessToken) > 16384
			|| preg_match('/\s|[\x00-\x1f\x7f]/', $accessToken))) {
			throw new \InvalidArgumentException('Invalid CSC access token.');
		}

		return new self(new CscClient(new NextcloudCscHttpClient($clientService, $systemConfig), $baseUrl, $accessToken), false);
	}

	/**
	 * Opt-in test fixture only. The endpoint must be HTTP loopback and must
	 * expose exclusively synthetic test certificates. Never route user requests
	 * or administrator-managed personal credentials through this factory.
	 */
	public static function forSyntheticLab(HttpClientInterface $httpClient, string $baseUrl): self {
		$baseUrl = self::validatedEndpoint(['baseUrl' => $baseUrl], true);

		return new self(new CscClient($httpClient, $baseUrl), true);
	}

	/** @return list<RemoteCertificate> */
	public function certificates(?string $identifier = null): array {
		return (new CscCertificateDiscovery($this->client))->certificates($identifier);
	}

	/**
	 * Invoke at most once for this laboratory operation. A failed submission
	 * must remain non-retryable until #9603 persists an authoritative outcome.
	 * SignerPHP v0.5.0 does not classify ambiguous post-signHash responses.
	 */
	public function sign(ExternalSigningPayload $payload, RemoteCertificate $certificate): SignatureValue {
		if (!$this->syntheticLab) {
			throw new \LogicException('CSC personal signing requires verified signer-bound authorization.');
		}
		if ($this->signAttempted) {
			throw new \LogicException('CSC signing was already attempted; reconcile the outcome before any resubmission.');
		}
		$this->signAttempted = true;

		return (new CscSignatureProvider($this->client))->sign($payload, $certificate);
	}

	/** @param array<string, mixed> $configuration */
	private static function validatedEndpoint(array $configuration, bool $lab): string {
		if (array_keys($configuration) !== ['baseUrl'] || !is_string($configuration['baseUrl'])
			|| $configuration['baseUrl'] === '' || strlen($configuration['baseUrl']) > 2048) {
			throw new \InvalidArgumentException('CSC configuration must contain only baseUrl; secrets are separate.');
		}
		$url = $configuration['baseUrl'];
		if (preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
			throw new \InvalidArgumentException('CSC endpoint contains prohibited characters.');
		}
		$parts = parse_url($url);
		if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])
			|| isset($parts['user']) || isset($parts['pass'])
			|| isset($parts['query']) || isset($parts['fragment'])) {
			throw new \InvalidArgumentException('Invalid CSC endpoint.');
		}
		$host = strtolower($parts['host']);
		$scheme = strtolower($parts['scheme']);
		if ($parts['scheme'] !== $scheme) {
			throw new \InvalidArgumentException('CSC endpoint scheme must be lowercase.');
		}
		if ($lab) {
			if ($scheme !== 'http' || !in_array($host, ['127.0.0.1', 'localhost'], true)) {
				throw new \InvalidArgumentException('CSC synthetic laboratory must use HTTP loopback.');
			}
		} elseif ($scheme !== 'https' || filter_var($host, FILTER_VALIDATE_IP)
			|| !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
			|| !str_contains($host, '.') || str_ends_with($host, '.')
			|| preg_match('/\.(?:localhost|local|internal|test|invalid)$/', $host)) {
			throw new \InvalidArgumentException('CSC endpoint must use a public HTTPS DNS name.');
		}
		$path = $parts['path'] ?? '';
		if (!preg_match('~^[/a-zA-Z0-9._~-]*$~', $path)
			|| preg_match('~(?:^|/)\.\.(?:/|$)|//~', $path)) {
			throw new \InvalidArgumentException('CSC endpoint has an unsafe path.');
		}

		return $url;
	}
}
