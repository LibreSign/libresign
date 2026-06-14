<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Crl;

use DateTime;
use OCA\Libresign\Db\CrlMapper;
use OCA\Libresign\Enum\CertificateEngineType;
use OCA\Libresign\Enum\CRLReason;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Service\Certificate\FileService;
use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;

/**
 * RFC 5280 compliant CRL management
 *
 * @psalm-import-type LibresignCrlCertificateStatusResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignCrlListResponse from \OCA\Libresign\ResponseDefinitions
 */
class CrlService {
	/**
	 * Distributed cache for generated CRL DER content.
	 *
	 * Cache entries are versioned per day so a CRL stays stable during the
	 * current day and is naturally refreshed by the next daily refresh cycle.
	 */
	private ICache $cache;

	/** How long (seconds) a generated CRL DER is kept in cache.
	 *
	 * Must be ≤ the CRL's own nextUpdate window, which AEngineHandler sets to
	 * 7 days (+7 days in createAndSignCrl). The daily cache key keeps recent
	 * CRL snapshots isolated while this TTL lets older daily entries age out
	 * automatically.
	 */
	private const GENERATED_CRL_TTL = 7 * 86400; // 7 days
	private const GENERATED_CRL_CACHE_DATE_FORMAT = 'Y-m-d';

	public function __construct(
		private CrlMapper $crlMapper,
		private LoggerInterface $logger,
		private CertificateEngineFactory $certificateEngineFactory,
		private CrlUrlParserService $crlUrlParserService,
		private FileService $certificateFileService,
		ICacheFactory $cacheFactory,
	) {
		$this->cache = $cacheFactory->createDistributed('libresign_crl_generated');
	}

	public function getRootCertificateFromCrlUrls(array $crlUrls): string {
		foreach ($crlUrls as $crlUrl) {
			$crlInfo = $this->crlUrlParserService->parseUrl($crlUrl);
			if ($crlInfo === null) {
				continue;
			}
			$engineType = CertificateEngineType::tryFromValue($crlInfo['engineType']);
			if ($engineType === null) {
				continue;
			}
			return $this->certificateFileService->getRootCertificateByGeneration(
				$crlInfo['instanceId'],
				$crlInfo['generation'],
				$engineType
			);
		}

		return '';
	}

	public function revokeCertificate(
		string $serialNumber,
		CRLReason $reason = CRLReason::UNSPECIFIED,
		?string $reasonText = null,
		?string $revokedBy = null,
		?DateTime $invalidityDate = null,
		?DateTime $revokedAt = null,
	): bool {

		try {
			$certificate = $this->crlMapper->findBySerialNumber($serialNumber);
			['instanceId' => $instanceId, 'generation' => $generation, 'engineType' => $engineType] = $this->getCrlMetadata($certificate);
			$crlNumber = $this->getNextCrlNumber($instanceId, $generation, $engineType);

			$this->crlMapper->revokeCertificateEntity(
				$certificate,
				$reason,
				$reasonText,
				$revokedBy,
				$invalidityDate,
				$crlNumber,
				$revokedAt,
			);

			return true;
		} catch (\Exception $exception) {
			$this->logger->warning('Failed to revoke certificate {serial}', [
				'serial' => $serialNumber,
				'error' => $exception->getMessage(),
			]);
			return false;
		}
	}

	/**
	 * Revoke all issued certificates owned by a user
	 *
	 * @param string $userId User ID whose certificates should be revoked
	 * @param CRLReason $reason Revocation reason
	 * @param string|null $reasonText Optional text describing the reason
	 * @param string|null $revokedBy Who is revoking the certificates
	 * @return int Number of certificates revoked
	 */
	public function revokeUserCertificates(
		string $userId,
		CRLReason $reason = CRLReason::UNSPECIFIED,
		?string $reasonText = null,
		?string $revokedBy = null,
	): int {
		$certificates = $this->crlMapper->findIssuedByOwner($userId);

		return $this->revokeCertificateList(
			$certificates,
			$reason,
			$reasonText,
			$revokedBy
		);
	}

	/**
	 * Revoke a list of certificates
	 *
	 * @param array<\OCA\Libresign\Db\Crl> $certificates Array of Crl entities
	 * @param CRLReason $reason Revocation reason
	 * @param string|null $reasonText Optional text describing the reason
	 * @param string|null $revokedBy Who is revoking the certificates
	 * @return int Number of certificates successfully revoked
	 */
	private function revokeCertificateList(
		array $certificates,
		CRLReason $reason,
		?string $reasonText = null,
		?string $revokedBy = null,
	): int {
		$revokedCount = 0;

		foreach ($certificates as $certificate) {
			try {
				['instanceId' => $instanceId, 'generation' => $generation, 'engineType' => $engineType] = $this->getCrlMetadata($certificate);
				$crlNumber = $this->getNextCrlNumber($instanceId, $generation, $engineType);

				$this->crlMapper->revokeCertificateEntity(
					$certificate,
					$reason,
					$reasonText,
					$revokedBy,
					null,
					$crlNumber
				);

				$revokedCount++;
			} catch (\Exception $e) {
				$this->logger->warning('Failed to revoke certificate {serial}', [
					'serial' => $certificate->getSerialNumber(),
					'error' => $e->getMessage(),
				]);
			}
		}

		return $revokedCount;
	}

	public function getCertificateStatus(string $serialNumber, ?DateTime $checkDate = null): array {
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

		} catch (\OCP\AppFramework\Db\DoesNotExistException) {
			return ['status' => 'unknown'];
		}
	}

	/**
	 * @return LibresignCrlCertificateStatusResponse
	 */
	public function getCertificateStatusResponse(string $serialNumber): array {
		$statusInfo = $this->getCertificateStatus($serialNumber);

		$response = [
			'serial_number' => $serialNumber,
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

	public function isInvalidAt(string $serialNumber, ?DateTime $checkDate = null): bool {
		return $this->crlMapper->isInvalidAt($serialNumber, $checkDate);
	}

	public function getRevokedCertificates(string $instanceId = '', int $generation = 0, string $engine = ''): array {
		$certificates = $this->crlMapper->getRevokedCertificates($instanceId, $generation, $engine);

		$result = [];
		foreach ($certificates as $certificate) {
			$result[] = [
				'serial_number' => $certificate->getSerialNumber(),
				'owner' => $certificate->getOwner(),
				'reason_code' => $certificate->getReasonCode(),
				'description' => $certificate->getReasonCode() ? CRLReason::from($certificate->getReasonCode())->getDescription() : null,
				'revoked_by' => $certificate->getRevokedBy(),
				'revoked_at' => $certificate->getRevokedAt()?->format('Y-m-d H:i:s'),
				'invalidity_date' => $certificate->getInvalidityDate()?->format('Y-m-d H:i:s'),
				'crl_number' => $certificate->getCrlNumber(),
			];
		}

		return $result;
	}

	/**
	 * @return array{instanceId: string, generation: int, engineType: string}
	 */
	private function getCrlMetadata(\OCA\Libresign\Db\Crl $certificate): array {
		$instanceId = $certificate->getInstanceId();
		$generation = $certificate->getGeneration();
		$engineType = $certificate->getEngine();

		if ($instanceId === null || $generation === null || $engineType === '') {
			throw new \RuntimeException('Certificate missing CRL metadata: instance_id, generation or engine');
		}

		return [
			'instanceId' => $instanceId,
			'generation' => $generation,
			'engineType' => $engineType,
		];
	}

	private function getNextCrlNumber(string $instanceId, int $generation, string $engineType): int {
		$lastCrlNumber = $this->crlMapper->getLastCrlNumber(
			$instanceId,
			$generation,
			$this->normalizeEngineType($engineType)->getEngineName()
		);

		return $lastCrlNumber + 1;
	}

	private function generatedCrlCacheKey(string $instanceId, int $generation, string $engineType): string {
		$normalizedEngineType = $this->normalizeEngineType($engineType)->value;
		$cacheDate = (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get())))
			->format(self::GENERATED_CRL_CACHE_DATE_FORMAT);

		return sha1($instanceId . '_' . $generation . '_' . $normalizedEngineType . '_' . $cacheDate);
	}

	private function normalizeEngineType(string $engineType): CertificateEngineType {
		$normalizedEngineType = CertificateEngineType::tryFromValue($engineType);
		if ($normalizedEngineType === null) {
			throw new \InvalidArgumentException("Invalid engine type: $engineType");
		}

		return $normalizedEngineType;
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

	public function refreshGeneratedCrlCache(): int {
		$refreshedScopes = 0;

		foreach ($this->crlMapper->listGeneratedCrlScopes() as $scope) {
			try {
				$this->refreshGeneratedCrlDer(
					$scope['instanceId'],
					$scope['generation'],
					$scope['engineType']
				);
				$refreshedScopes++;
			} catch (\Exception $exception) {
				$this->logger->warning('Failed to refresh generated CRL cache for {instanceId}/{generation}/{engineType}', [
					'instanceId' => $scope['instanceId'],
					'generation' => $scope['generation'],
					'engineType' => $scope['engineType'],
					'error' => $exception->getMessage(),
				]);
			}
		}

		return $refreshedScopes;
	}

	public function generateCrlDer(string $instanceId, int $generation, string $engineType): string {
		$cacheKey = $this->generatedCrlCacheKey($instanceId, $generation, $engineType);
		$cached = $this->cache->get($cacheKey);
		if ($cached !== null) {
			return $cached;
		}

		return $this->refreshGeneratedCrlDer($instanceId, $generation, $engineType);
	}

	public function refreshGeneratedCrlDer(string $instanceId, int $generation, string $engineType): string {
		try {
			$crlDer = $this->buildCrlDer($instanceId, $generation, $engineType);
			$this->cache->set(
				$this->generatedCrlCacheKey($instanceId, $generation, $engineType),
				$crlDer,
				self::GENERATED_CRL_TTL
			);

			return $crlDer;
		} catch (\Throwable $e) {
			if ($e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'Config path does not exist for instanceId:')) {
				$this->logger->debug('Skipping local CRL generation because source PKI config path is missing', [
					'instanceId' => $instanceId,
					'generation' => $generation,
					'engineType' => $engineType,
					'reason' => $e->getMessage(),
				]);
			} else {
				$this->logger->error('Failed to generate CRL', [
					'exception' => $e,
				]);
			}
			throw $e;
		}
	}

	private function buildCrlDer(string $instanceId, int $generation, string $engineType): string {
		$revokedCertificates = $this->crlMapper->getRevokedCertificates($instanceId, $generation, $engineType);

		$engine = $this->certificateEngineFactory->getEngine();

		if (!method_exists($engine, 'generateCrlDer')) {
			throw new \RuntimeException('Current certificate engine does not support CRL generation');
		}

		$crlNumber = $this->getNextCrlNumber($instanceId, $generation, $engineType);

		return $engine->generateCrlDer($revokedCertificates, $instanceId, $generation, $crlNumber);
	}

	/**
	 * List CRL entries with pagination and filters
	 *
	 * @param int|null $page Page number (1-based), defaults to 1
	 * @param int|null $length Number of items per page, defaults to 100
	 * @param array<string, mixed> $filter Filters to apply (status, engine, instance_id, owner, serial_number, revoked_by, generation)
	 * @param array<string, string> $sort Sort fields and directions ['field' => 'ASC|DESC']
	 * @return LibresignCrlListResponse
	 */
	public function listCrlEntries(
		?int $page = null,
		?int $length = null,
		array $filter = [],
		array $sort = [],
	): array {
		$page ??= 1;
		$length ??= 100;

		$result = $this->crlMapper->listWithPagination($page, $length, $filter, $sort);

		$formattedData = array_values(array_map(fn ($entity) => [
			'id' => $entity->getId(),
			'serial_number' => $entity->getSerialNumber(),
			'owner' => $entity->getOwner(),
			'status' => $entity->getStatus(),
			'certificate_type' => $entity->getCertificateType(),
			'engine' => $entity->getEngine(),
			'instance_id' => $entity->getInstanceId(),
			'generation' => $entity->getGeneration(),
			'issued_at' => $entity->getIssuedAt()?->format('Y-m-d H:i:s'),
			'valid_to' => $entity->getValidTo()?->format('Y-m-d H:i:s'),
			'revoked_at' => $entity->getRevokedAt()?->format('Y-m-d H:i:s'),
			'reason_code' => $entity->getReasonCode(),
			'comment' => $entity->getComment(),
			'revoked_by' => $entity->getRevokedBy(),
			'invalidity_date' => $entity->getInvalidityDate()?->format('Y-m-d H:i:s'),
			'crl_number' => $entity->getCrlNumber(),
		], $result['data']));

		return [
			'data' => $formattedData,
			'total' => $result['total'],
			'page' => $page,
			'length' => $length,
		];
	}
}
