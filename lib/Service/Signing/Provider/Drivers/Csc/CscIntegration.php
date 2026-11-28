<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\Provider\Drivers\Csc;

use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\ExternalSigningPayload;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureValue;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Infrastructure\Native\Contract\HttpClientInterface;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Infrastructure\Remote\Csc\CscCertificateDiscovery;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Infrastructure\Remote\Csc\CscClient;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Infrastructure\Remote\Csc\CscSignatureProvider;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\RemoteSigning\Contract\RemoteCertificateDiscoveryInterface;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\RemoteSigning\Contract\RemoteSignatureProviderInterface;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\RemoteSigning\DTO\RemoteCertificate;
use OCP\Http\Client\IClientService;
use OCP\IConfig;

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
		private readonly ?string $syntheticLabEndpoint = null,
		private readonly ?string $syntheticCertificateSha256 = null,
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

		return new self(new CscClient(new NextcloudHttpTransport($clientService, $systemConfig), $baseUrl, $accessToken), false);
	}

	/**
	 * Operationally pinned synthetic CSC laboratory. The administrator may
	 * configure a public HTTPS CSC instance, but a server operator MUST also
	 * pin both its exact endpoint and the test certificate SHA-256 fingerprint
	 * in the Nextcloud system configuration. A configured administrator token
	 * cannot authorize this factory and is never accepted here.
	 *
	 * This opt-in is not proof of personal signer authentication, certificate
	 * qualification or trust in production CSC providers.
	 *
	 * @param array{baseUrl: string} $configuration
	 */
	public static function fromConfiguredSyntheticLab(
		IClientService $clientService,
		IConfig $systemConfig,
		array $configuration,
	): self {
		// Reuse all Nextcloud SSRF, public HTTPS, DNS pinning and redirect
		// restrictions of the normal configured CSC transport.
		$normal = self::fromConfiguredInstance($clientService, $systemConfig, $configuration);
		$baseUrl = self::validatedEndpoint($configuration, false);
		$pinnedUrl = $systemConfig->getSystemValueString('libresign_csc_synthetic_lab_url', '');
		$fingerprint = $systemConfig->getSystemValueString('libresign_csc_synthetic_lab_certificate_sha256', '');
		if ($pinnedUrl === '' || !hash_equals($pinnedUrl, $baseUrl)
			|| preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1) {
			throw new \DomainException('CSC synthetic laboratory endpoint or certificate is not pinned by the server operator');
		}
		return new self($normal->client, true, $baseUrl, $fingerprint);
	}

	/**
	 * Opt-in test fixture only. The endpoint must be HTTP loopback and must
	 * expose exclusively synthetic test certificates. Never route user requests
	 * or administrator-managed personal credentials through this factory.
	 */
	public static function forSyntheticLab(HttpClientInterface $httpClient, string $baseUrl): self {
		$baseUrl = self::validatedEndpoint(['baseUrl' => $baseUrl], true);

		return new self(new CscClient($httpClient, $baseUrl), true, $baseUrl);
	}

	/**
	 * Internal synthetic-laboratory proof for the CSC v1 execution path.
	 * Merely having a configured integration or administrator access token is
	 * never sufficient to authorize a real signer private-key operation.
	 */
	public function assertSyntheticLaboratory(string $configuredEndpoint): void {
		if (!$this->syntheticLab || $this->syntheticLabEndpoint === null
			|| !hash_equals($this->syntheticLabEndpoint, $configuredEndpoint)) {
			throw new \DomainException('CSC signing is restricted to the configured synthetic laboratory');
		}
	}

	/** @return list<RemoteCertificate> */
	#[\Override]
	public function certificates(?string $identifier = null): array {
		$certificates = (new CscCertificateDiscovery($this->client))->certificates($identifier);
		if ($this->syntheticCertificateSha256 === null) {
			return $certificates;
		}
		return array_values(array_filter($certificates,
			fn (RemoteCertificate $certificate): bool => $this->hasPinnedSyntheticCertificate($certificate)));
	}

	/**
	 * Invoke at most once for this laboratory operation. A failed submission
	 * must remain non-retryable until #9603 persists an authoritative outcome.
	 * SignerPHP v0.5.1 classifies unknown post-signHash outcomes; the durable
	 * session workflow must preserve uncertainty without automatic resubmission.
	 */
	#[\Override]
	public function sign(ExternalSigningPayload $payload, RemoteCertificate $certificate): SignatureValue {
		if (!$this->syntheticLab) {
			throw new \LogicException('CSC personal signing requires verified signer-bound authorization.');
		}
		if ($this->syntheticCertificateSha256 !== null && !$this->hasPinnedSyntheticCertificate($certificate)) {
			throw new \DomainException('CSC credential certificate does not match the pinned synthetic laboratory');
		}
		if ($this->signAttempted) {
			throw new \LogicException('CSC signing was already attempted; reconcile the outcome before any resubmission.');
		}
		$this->signAttempted = true;

		return (new CscSignatureProvider($this->client))->sign($payload, $certificate);
	}

	private function hasPinnedSyntheticCertificate(RemoteCertificate $certificate): bool {
		if ($this->syntheticCertificateSha256 === null) {
			return false;
		}
		$fingerprint = @openssl_x509_fingerprint($certificate->certificate->certificatePem, 'sha256');
		return is_string($fingerprint) && hash_equals($this->syntheticCertificateSha256, strtolower($fingerprint));
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
		if (!preg_match('#^[/a-zA-Z0-9._~-]*$#', $path)
			|| preg_match('~(?:^|/)\.\.(?:/|$)|//~', $path)) {
			throw new \InvalidArgumentException('CSC endpoint has an unsafe path.');
		}

		return $url;
	}
}
