<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use DateTime;
use OCA\Libresign\Db\CrlMapper;
use OCA\Libresign\Enum\CRLReason;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use Psr\Log\LoggerInterface;

/**
 * RFC 5280 compliant CRL management
 */
class CrlService {

	public function __construct(
		private CrlMapper $crlMapper,
		private LoggerInterface $logger,
		private CertificateEngineFactory $certificateEngineFactory,
	) {
	}

	private static function isValidReasonCode(int $reasonCode): bool {
		return CRLReason::isValid($reasonCode);
	}



	public function revokeCertificate(
		int $serialNumber,
		int $reasonCode = CRLReason::UNSPECIFIED->value,
		?string $reasonText = null,
		?string $revokedBy = null,
		?DateTime $invalidityDate = null,
	): bool {
		if (!self::isValidReasonCode($reasonCode)) {
			throw new \InvalidArgumentException("Invalid CRLReason code: {$reasonCode}");
		}

		$reason = CRLReason::from($reasonCode);
		$crlNumber = $this->getNextCrlNumber();

		try {
			$this->crlMapper->revokeCertificate(
				$serialNumber,
				$reason,
				$reasonText,
				$revokedBy,
				$invalidityDate,
				$crlNumber
			);

			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function getCertificateStatus(int $serialNumber, ?DateTime $checkDate = null): array {
		try {
			$certificate = $this->crlMapper->findBySerialNumber($serialNumber);

			if ($certificate->isRevoked()) {
				return [
					'status' => 'revoked',
					'reason_code' => $certificate->getReasonCode(),
					'revoked_at' => $certificate->getRevokedAt()?->format('Y-m-d\TH:i:s\Z'),
				];
			}

			if ($certificate->isExpired()) {
				return [
					'status' => 'expired',
					'valid_to' => $certificate->getValidTo()?->format('Y-m-d\TH:i:s\Z'),
				];
			}

			return ['status' => 'valid'];

		} catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
			return ['status' => 'unknown'];
		}
	}

	public function getCertificateStatusResponse(int $serialNumber): array {
		$statusInfo = $this->getCertificateStatus($serialNumber);

		$response = [
			'serial_number' => (string)$serialNumber,
			'status' => $statusInfo['status'],
			'checked_at' => (new \DateTime())->format('Y-m-d\TH:i:s\Z'),
		];

		if ($statusInfo['status'] === 'revoked') {
			if (isset($statusInfo['reason_code'])) {
				$response['reason_code'] = $statusInfo['reason_code'];
			}
			if (isset($statusInfo['revoked_at'])) {
				$response['revoked_at'] = $statusInfo['revoked_at'];
			}
		}

		if ($statusInfo['status'] === 'expired') {
			if (isset($statusInfo['valid_to'])) {
				$response['valid_to'] = $statusInfo['valid_to'];
			}
		}

		return $response;
	}

	public function isInvalidAt(int $serialNumber, ?DateTime $checkDate = null): bool {
		return $this->crlMapper->isInvalidAt($serialNumber, $checkDate);
	}

	public function getRevokedCertificates(): array {
		$certificates = $this->crlMapper->getRevokedCertificates();

		$result = [];
		foreach ($certificates as $certificate) {
			$result[] = [
				'serial_number' => $certificate->getSerialNumber(),
				'owner' => $certificate->getOwner(),
				'reason_code' => $certificate->getReasonCode(),
				'reason_description' => $certificate->getReasonCode() ? CRLReason::from($certificate->getReasonCode())->getDescription() : null,
				'revoked_by' => $certificate->getRevokedBy(),
				'revoked_at' => $certificate->getRevokedAt()?->format('Y-m-d H:i:s'),
				'invalidity_date' => $certificate->getInvalidityDate()?->format('Y-m-d H:i:s'),
				'crl_number' => $certificate->getCrlNumber(),
			];
		}

		return $result;
	}

	public function getNextCrlNumber(): int {
		return $this->crlMapper->getNextCrlNumber();
	}

	public function cleanupExpiredCertificates(?DateTime $before = null): int {
		return $this->crlMapper->cleanupExpiredCertificates($before);
	}

	public function getStatistics(): array {
		return $this->crlMapper->getStatistics();
	}

	public function getRevocationStatistics(): array {
		return $this->crlMapper->getRevocationStatistics();
	}

	/**
	 * @inheritDoc
	 */
	public function generateCrlDer(): string {
		try {
			$revokedCertificates = $this->crlMapper->getRevokedCertificates();

			$engine = $this->certificateEngineFactory->getEngine();

			if (method_exists($engine, 'generateCrlDer')) {
				return $engine->generateCrlDer($revokedCertificates);
			}

			throw new \RuntimeException('Current certificate engine does not support CRL generation');
		} catch (\Throwable $e) {
			$this->logger->error('Failed to generate CRL', [
				'exception' => $e,
			]);
			throw $e;
		}
	}

}
