<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signature;

use DateTime;
use LibreSign\PdfSignatureValidator\Parser\PdfSignatureValidator;
use LibreSign\PdfSignatureValidator\Model\ValidationResult;
use LibreSign\PdfSignatureValidator\Model\ValidationState;
use OCA\Libresign\AppInfo\Application;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Service to validate PDF signatures using the pdf-signature-validator package.
 *
 * This replaces shell calls to pdfsig with pure PHP validation.
 * Supports custom trusted roots (e.g., LibreSign CA) to recognize
 * certificates without requiring system-level CA registration.
 */
class PdfSignatureValidationService {
	private PdfSignatureValidator $validator;
	private string $libresignCaCertificate = '';

	public function __construct(
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
		$this->validator = new PdfSignatureValidator();
		$this->loadLibreSignCaCertificate();
	}

	/**
	 * Load LibreSign CA certificate and set it as trusted root.
	 */
	private function loadLibreSignCaCertificate(): void {
		// Try to load from config path first
		$configPath = $this->appConfig->getValueString(Application::APP_ID, 'config_path');
		if (!empty($configPath) && is_dir($configPath)) {
			$caPemPath = $configPath . DIRECTORY_SEPARATOR . 'ca.pem';
			if (is_readable($caPemPath)) {
				$cert = @file_get_contents($caPemPath);
				if ($cert !== false) {
					$this->libresignCaCertificate = $cert;
					$this->validator->addTrustedRoot($cert);
					return;
				}
			}
		}

		// Try alternate location
		$alternateConfig = $this->appConfig->getValueString(
			Application::APP_ID,
			'libresign_ca_certificate'
		);
		if (!empty($alternateConfig)) {
			$this->libresignCaCertificate = $alternateConfig;
			$this->validator->addTrustedRoot($alternateConfig);
		}
	}

	/**
	 * Add additional trusted root certificate.
	 */
	public function addTrustedRoot(string $certificatePem): void {
		$this->validator->addTrustedRoot($certificatePem);
	}

	/**
	 * Set multiple trusted root certificates.
	 *
	 * @param list<string> $certificates
	 */
	public function setTrustedRoots(array $certificates): void {
		$this->validator->setTrustedRoots($certificates);
	}

	/**
	 * Validate PDF signatures from file resource.
	 *
	 * @param resource $resource PDF file resource
	 * @param ?\DateTime $signatureTime Optional time to validate against (for historic validation)
	 * @return list<array{signatureValidation: array, certificateValidation: array}>
	 */
	public function validateFromResource($resource, ?DateTime $signatureTime = null): array {
		try {
			$results = $this->validator->validateFromResource($resource);
			return $this->mapValidationResults($results, $signatureTime);
		} catch (\Throwable $e) {
			$this->logger->warning('PDF signature validation failed', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);
			return [];
		}
	}

	/**
	 * Validate PDF signatures from binary content.
	 *
	 * @param string $pdfContent Binary PDF content
	 * @param ?\DateTime $signatureTime Optional time to validate against (for historic validation)
	 * @return list<array{signatureValidation: array, certificateValidation: array}>
	 */
	public function validateFromString(string $pdfContent, ?DateTime $signatureTime = null): array {
		try {
			$results = $this->validator->validateFromString($pdfContent);
			return $this->mapValidationResults($results, $signatureTime);
		} catch (\Throwable $e) {
			$this->logger->warning('PDF signature validation failed', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);
			return [];
		}
	}

	/**
	 * Map validation results from PdfSignatureValidator to LibreSign format.
	 *
	 * @param list<array> $results Results from PdfSignatureValidator
	 * @param ?\DateTime $signatureTime
	 * @return list<array{signatureValidation: array, certificateValidation: array}>
	 */
	private function mapValidationResults(array $results, ?DateTime $signatureTime = null): array {
		$mapped = [];

		foreach ($results as $result) {
			$sigValidation = $result['signatureValidation'] ?? null;
			$certValidation = $result['certificateValidation'] ?? null;

			if (!$sigValidation instanceof ValidationResult || !$certValidation instanceof ValidationResult) {
				continue;
			}

			$mapped[] = [
				'signatureValidation' => $this->mapSignatureValidation($sigValidation),
				'certificateValidation' => $this->mapCertificateValidation($certValidation),
				'raw' => [
					'signature' => $sigValidation,
					'certificate' => $certValidation,
				],
			];
		}

		return $mapped;
	}

	/**
	 * Map signature validation result to LibreSign format.
	 */
	private function mapSignatureValidation(ValidationResult $result): array {
		return match ($result->state) {
			ValidationState::SIGNATURE_VALID => [
				'id' => 1,
				'label' => 'Signature is valid.',
				'isValid' => true,
			],
			ValidationState::SIGNATURE_INVALID => [
				'id' => 2,
				'label' => 'Signature is invalid.',
				'reason' => $result->reason,
				'isValid' => false,
			],
			ValidationState::DIGEST_MISMATCH => [
				'id' => 3,
				'label' => 'Digest mismatch.',
				'reason' => $result->reason,
				'isValid' => false,
			],
			ValidationState::NOT_VERIFIED => [
				'id' => 5,
				'label' => 'Signature has not yet been verified.',
				'reason' => $result->reason,
				'isValid' => false,
			],
			default => [
				'id' => 6,
				'label' => 'Unknown validation failure.',
				'reason' => $result->reason,
				'isValid' => false,
			],
		};
	}

	/**
	 * Map certificate validation result to LibreSign format.
	 */
	private function mapCertificateValidation(ValidationResult $result): array {
		return match ($result->state) {
			ValidationState::CERT_TRUSTED => [
				'id' => 1,
				'label' => 'Certificate is trusted.',
				'isValid' => true,
			],
			ValidationState::CERT_ISSUER_NOT_TRUSTED => [
				'id' => 2,
				'label' => "Certificate issuer isn't trusted.",
				'reason' => $result->reason,
				'isValid' => false,
			],
			ValidationState::CERT_ISSUER_UNKNOWN => [
				'id' => 3,
				'label' => 'Certificate issuer is unknown.',
				'reason' => $result->reason,
				'isValid' => false,
			],
			ValidationState::CERT_REVOKED => [
				'id' => 4,
				'label' => 'Certificate has been revoked.',
				'reason' => $result->reason,
				'isValid' => false,
			],
			ValidationState::CERT_EXPIRED => [
				'id' => 5,
				'label' => 'Certificate has expired.',
				'reason' => $result->reason,
				'isValid' => false,
			],
			ValidationState::CERT_NOT_VERIFIED => [
				'id' => 6,
				'label' => 'Certificate has not yet been verified.',
				'reason' => $result->reason,
				'isValid' => false,
			],
			default => [
				'id' => 7,
				'label' => 'Unknown issue with certificate or corrupted data.',
				'reason' => $result->reason,
				'isValid' => false,
			],
		};
	}

	/**
	 * Check if LibreSign CA is loaded.
	 */
	public function isLibreSignCaLoaded(): bool {
		return !empty($this->libresignCaCertificate);
	}

	/**
	 * Get LibreSign CA certificate (PEM format).
	 */
	public function getLibreSignCaCertificate(): string {
		return $this->libresignCaCertificate;
	}
}
