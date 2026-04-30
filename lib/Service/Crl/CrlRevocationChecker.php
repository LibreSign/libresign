<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Crl;

use OCA\Libresign\Enum\CrlValidationStatus;
use OCA\Libresign\Service\Crl\Ldap\LdapCrlDownloader;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\CrlValidation\CrlValidationPolicy;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\ITempManager;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * Verifies whether a certificate has been revoked by checking its embedded
 * CRL Distribution Point URLs. Supports HTTP/HTTPS, LDAP (RFC 4516), and
 * local LibreSign-managed CRL endpoints.
 */
class CrlRevocationChecker {
	/** Cached result of {@see getLocalCrlPattern()} — built once per request. */
	private ?string $localCrlPattern = null;

	/** Distributed cache for externally downloaded CRL content (TTL: 24 h). */
	private ICache $cache;

	public function __construct(
		private IConfig $config,
		private PolicyService $policyService,
		private IURLGenerator $urlGenerator,
		private ITempManager $tempManager,
		private LoggerInterface $logger,
		ICacheFactory $cacheFactory,
		private LdapCrlDownloader $ldapDownloader,
	) {
		$this->cache = $cacheFactory->createDistributed('libresign_crl');
	}

	/**
	 * Validate a certificate against the CRL Distribution Points found in its
	 * data. Returns an array with a 'status' key (always {@see CrlValidationStatus})
	 * and optionally 'revoked_at' (ISO 8601) when the certificate is revoked.
	 *
	 * @return array{status: CrlValidationStatus, revoked_at?: string}
	 */
	public function validate(array $crlUrls, string $certPem): array {
		return $this->validateFromUrlsWithDetails($crlUrls, $certPem);
	}

	/**
	 * Internal validation worker that iterates through CRL distribution points
	 * and returns the validation status from the first accessible/conclusive point.
	 *
	 * @return array{status: CrlValidationStatus, revoked_at?: string}
	 */
	private function validateFromUrlsWithDetails(array $crlUrls, string $certPem): array {
		$externalValidationEnabled = $this->policyService->resolve(CrlValidationPolicy::KEY)->getEffectiveValueAsBool(true);

		if (empty($crlUrls)) {
			// When external validation is disabled, treat an empty distribution-point
			// list the same as if all points were intentionally skipped.
			if (!$externalValidationEnabled) {
				return ['status' => CrlValidationStatus::DISABLED];
			}
			return ['status' => CrlValidationStatus::NO_URLS];
		}

		$accessibleUrls = 0;
		$disabledUrls = 0;
		foreach ($crlUrls as $crlUrl) {
			try {
				$isLocal = $this->isLocalCrlUrl($crlUrl);
				// Skip external CRL validation when disabled by admin, but always
				// validate local LibreSign-managed CRLs.
				if (!$externalValidationEnabled && !$isLocal) {
					$disabledUrls++;
					continue;
				}
				$validationResult = $this->downloadAndValidateWithDetails($crlUrl, $certPem, $isLocal);
				if ($validationResult['status'] === CrlValidationStatus::VALID) {
					return $validationResult;
				}
				if ($validationResult['status'] === CrlValidationStatus::REVOKED) {
					return $validationResult;
				}
				// Only count as accessible if we actually reached the server and parsed
				// a CRL response – validation_error means the download itself failed.
				if ($validationResult['status'] !== CrlValidationStatus::VALIDATION_ERROR) {
					$accessibleUrls++;
				}
			} catch (\Exception) {
				continue;
			}
		}

		// All distribution points were intentionally skipped because the admin
		// disabled external CRL validation.
		if ($disabledUrls > 0 && $accessibleUrls === 0) {
			return ['status' => CrlValidationStatus::DISABLED];
		}

		if ($accessibleUrls === 0) {
			return ['status' => CrlValidationStatus::URLS_INACCESSIBLE];
		}

		return ['status' => CrlValidationStatus::VALIDATION_FAILED];
	}

	/**
	 * Download and validate CRL content from a single source URL.
	 *
	 * @return array{status: CrlValidationStatus, revoked_at?: string}
	 */
	private function downloadAndValidateWithDetails(string $crlUrl, string $certPem, bool $isLocal): array {
		try {
			if ($isLocal) {
				$crlContent = $this->generateLocalCrl($crlUrl);
			} elseif ($this->ldapDownloader->isLdapUrl($crlUrl)) {
				$crlContent = $this->ldapDownloader->download($crlUrl);
			} else {
				$crlContent = $this->downloadCrlContent($crlUrl);
			}

			if (!$crlContent) {
				throw new \Exception('Failed to get CRL content');
			}

			return $this->checkCertificateInCrlWithDetails($certPem, $crlContent);

		} catch (\Exception) {
			return ['status' => CrlValidationStatus::VALIDATION_ERROR];
		}
	}

	private function isLocalCrlUrl(string $url): bool {
		$host = parse_url($url, PHP_URL_HOST);
		if (!$host) {
			return false;
		}

		$trustedDomains = $this->config->getSystemValue('trusted_domains', []);

		return in_array($host, $trustedDomains, true);
	}

	private function generateLocalCrl(string $crlUrl): ?string {
		try {
			$pattern = $this->getLocalCrlPattern();
			if (preg_match($pattern, $crlUrl, $matches)) {
				$instanceId = $matches[1];
				$generation = (int)$matches[2];
				$engineType = $matches[3];

				// Lazy-loaded to avoid a circular dependency:
				// CrlService → CertificateEngineFactory → OpenSslHandler → CrlRevocationChecker → CrlService
				/** @var \OCA\Libresign\Service\Crl\CrlService */
				$crlService = \OCP\Server::get(\OCA\Libresign\Service\Crl\CrlService::class);

				return $crlService->generateCrlDer($instanceId, $generation, $engineType);
			}

			$this->logger->debug('CRL URL does not match expected pattern', ['url' => $crlUrl, 'pattern' => $pattern]);
			return null;
		} catch (\Exception $e) {
			if ($e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'Config path does not exist for instanceId:')) {
				$this->logger->debug('Skipping local CRL generation because source PKI config path is missing', [
					'reason' => $e->getMessage(),
				]);
			} else {
				$this->logger->warning('Failed to generate local CRL: ' . $e->getMessage());
			}
			return null;
		}
	}

	/**
	 * Builds and memoises the regex pattern used to recognise local LibreSign
	 * CRL URLs. The pattern is constructed once per request from the configured
	 * URL generator and then cached in a property to avoid redundant work on
	 * installations that validate many certificates in a single request.
	 */
	private function getLocalCrlPattern(): string {
		if ($this->localCrlPattern !== null) {
			return $this->localCrlPattern;
		}

		$templateUrl = $this->urlGenerator->linkToRouteAbsolute('libresign.crl.getRevocationList', [
			'instanceId' => 'INSTANCEID',
			'generation' => 999999,
			'engineType' => 'ENGINETYPE',
		]);

		$patternUrl = str_replace('INSTANCEID', '([^/_]+)', $templateUrl);
		$patternUrl = str_replace('999999', '(\d+)', $patternUrl);
		$patternUrl = str_replace('ENGINETYPE', '([^/_]+)', $patternUrl);

		$escapedPattern = str_replace([':', '/', '.'], ['\:', '\/', '\.'], $patternUrl);
		$escapedPattern = str_replace('\/apps\/', '(?:\/index\.php)?\/apps\/', $escapedPattern);

		$this->localCrlPattern = '/^' . $escapedPattern . '$/';
		return $this->localCrlPattern;
	}

	private function downloadCrlContent(string $url): ?string {
		if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'])) {
			return null;
		}

		$cacheKey = sha1($url);
		$cached = $this->cache->get($cacheKey);
		if ($cached !== null) {
			return $cached;
		}

		$context = stream_context_create([
			'http' => [
				'timeout' => 30,
				'user_agent' => 'LibreSign/1.0 CRL Validator',
				'follow_location' => 1,
				'max_redirects' => 3,
			]
		]);

		$content = @file_get_contents($url, false, $context);
		if ($content === false) {
			return null;
		}

		$this->cache->set($cacheKey, $content, 86400);
		return $content;
	}

	protected function isSerialNumberInCrl(string $crlText, string $serialNumber): bool {
		$normalizedSerial = strtoupper($serialNumber);
		$normalizedSerial = ltrim($normalizedSerial, '0') ?: '0';

		return preg_match('/Serial Number: 0*' . preg_quote($normalizedSerial, '/') . '/', $crlText) === 1;
	}

	/**
	 * Check if certificate serial is revoked in the provided CRL content.
	 *
	 * @return array{status: CrlValidationStatus, revoked_at?: string}
	 */
	private function checkCertificateInCrlWithDetails(string $certPem, string $crlContent): array {
		try {
			$certResource = openssl_x509_read($certPem);
			if (!$certResource) {
				return ['status' => CrlValidationStatus::VALIDATION_ERROR];
			}

			$certData = openssl_x509_parse($certResource);
			if (!isset($certData['serialNumber'])) {
				return ['status' => CrlValidationStatus::VALIDATION_ERROR];
			}

			$tempCrlFile = $this->tempManager->getTemporaryFile('.crl');
			file_put_contents($tempCrlFile, $crlContent);

			try {
				[$output, $exitCode] = $this->execOpenSslCrl($tempCrlFile);

				if ($exitCode !== 0) {
					return ['status' => CrlValidationStatus::VALIDATION_ERROR];
				}

				$crlText = implode("\n", $output);
				$serialCandidates = [$certData['serialNumber']];
				if (!empty($certData['serialNumberHex'])) {
					$serialCandidates[] = $certData['serialNumberHex'];
				}

				foreach ($serialCandidates as $serial) {
					if ($this->isSerialNumberInCrl($crlText, $serial)) {
						$revokedAt = $this->extractRevocationDateFromCrlText($crlText, $serialCandidates);
						return array_filter([
							'status' => CrlValidationStatus::REVOKED,
							'revoked_at' => $revokedAt,
						]);
					}
				}

				return ['status' => CrlValidationStatus::VALID];

			} finally {
				if (file_exists($tempCrlFile)) {
					unlink($tempCrlFile);
				}
			}

		} catch (\Exception) {
			return ['status' => CrlValidationStatus::VALIDATION_ERROR];
		}
	}

	/**
	 * Runs `openssl crl -text -noout` on the given DER file and returns
	 * [output_lines[], exit_code]. Extracted to allow test subclasses to
	 * override it without executing a real process.
	 */
	protected function execOpenSslCrl(string $tempCrlFile): array {
		$cmd = sprintf(
			'openssl crl -in %s -inform DER -text -noout',
			escapeshellarg($tempCrlFile)
		);
		exec($cmd, $output, $exitCode);
		return [$output, $exitCode];
	}

	protected function extractRevocationDateFromCrlText(string $crlText, array $serialNumbers): ?string {
		foreach ($serialNumbers as $serial) {
			$normalizedSerial = strtoupper(ltrim((string)$serial, '0')) ?: '0';
			$pattern = '/Serial Number:\s*0*' . preg_quote($normalizedSerial, '/') . '\s*\R\s*Revocation Date:\s*([^\r\n]+)/i';
			if (preg_match($pattern, $crlText, $matches) !== 1) {
				continue;
			}
			$dateText = trim($matches[1]);
			try {
				$date = new \DateTimeImmutable($dateText, new \DateTimeZone('UTC'));
				return $date->setTimezone(new \DateTimeZone('UTC'))->format(\DateTimeInterface::ATOM);
			} catch (\Exception) {
				continue;
			}
		}
		return null;
	}
}
