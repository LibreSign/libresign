<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Crl;

use DateTime;
use OCA\Libresign\Db\Crl as CrlEntity;
use OCA\Libresign\Db\CrlMapper;
use OCA\Libresign\Enum\CertificateEngineType;
use OCA\Libresign\Enum\CRLReason;
use OCA\Libresign\Enum\CRLStatus;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Service\Certificate\FileService;
use OCP\ICacheFactory;
use OCP\IMemcache;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type LibresignCrlCertificateStatusResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignCrlListItem from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignCrlListResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-type LibresignCrlStatusInfo = array{status: 'valid'}|array{status: 'unknown'}|array{status: 'expired', valid_to?: string}|array{status: 'revoked', reason_code?: null|int, revoked_at?: string}
 *
 * RFC 5280 compliant CRL management
 */
class CrlService {
	/**
	 * Short-lived distributed lock used only to avoid CRL regeneration stampedes.
	 */
	private IMemcache $lockCache;

	/** How long (seconds) a generated CRL remains reusable as a stale fallback.
	 *
	 * Must be ≤ the CRL's own nextUpdate window, which AEngineHandler sets to
	 * 7 days (+7 days in createAndSignCrl).
	 */
	private const GENERATED_CRL_MAX_STALE_AGE = 7 * 86400; // 7 days
	private const GENERATED_CRL_REFRESH_DATE_FORMAT = 'Y-m-d';
	private const GENERATED_CRL_LOCK_TTL = 60;
	private const GENERATED_CRL_LOCK_WAIT_USEC = 100000;
	private const GENERATED_CRL_LOCK_WAIT_ATTEMPTS = 10;

	public function __construct(
		private CrlMapper $crlMapper,
		private LoggerInterface $logger,
		private CertificateEngineFactory $certificateEngineFactory,
		private CrlUrlParserService $crlUrlParserService,
		private FileService $certificateFileService,
		ICacheFactory $cacheFactory,
		private GeneratedCrlStorageService $generatedCrlStorage,
	) {
		$this->lockCache = $cacheFactory->createLocking('libresign_crl_generated');
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
			$this->invalidateGeneratedCrlCache($instanceId, $generation, $engineType);

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
		$invalidatedScopes = [];

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

				$scopeKey = $this->generatedCrlStorage->getScopeKey($instanceId, $generation, $engineType);
				if (!isset($invalidatedScopes[$scopeKey])) {
					$this->invalidateGeneratedCrlCache($instanceId, $generation, $engineType);
					$invalidatedScopes[$scopeKey] = true;
				}

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

	/**
	 * @return LibresignCrlStatusInfo
	 */
	public function getCertificateStatus(string $serialNumber, ?DateTime $checkDate = null): array {
		try {
			$certificate = $this->crlMapper->findBySerialNumber($serialNumber);

			if ($certificate->isRevoked()) {
				$response = [
					'status' => 'revoked',
					'reason_code' => $certificate->getReasonCode(),
				];

				$revokedAt = $certificate->getRevokedAt()?->format('Y-m-d\TH:i:s\Z');
				if ($revokedAt !== null) {
					$response['revoked_at'] = $revokedAt;
				}

				return $response;
			}

			if ($certificate->isExpired()) {
				$response = ['status' => 'expired'];

				$validTo = $certificate->getValidTo()?->format('Y-m-d\TH:i:s\Z');
				if ($validTo !== null) {
					$response['valid_to'] = $validTo;
				}

				return $response;
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

		/** @var LibresignCrlCertificateStatusResponse $response */
		$response = [
			'serial_number' => $serialNumber,
			'status' => $statusInfo['status'],
			'checked_at' => (new \DateTime())->format('Y-m-d\TH:i:s\Z'),
		];

		if ($statusInfo['status'] === 'revoked') {
			if (array_key_exists('reason_code', $statusInfo)) {
				$response['reason_code'] = $statusInfo['reason_code'];
			}
			if (array_key_exists('revoked_at', $statusInfo)) {
				$response['revoked_at'] = $statusInfo['revoked_at'];
			}
		}

		if ($statusInfo['status'] === 'expired') {
			if (array_key_exists('valid_to', $statusInfo)) {
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

		return sha1($instanceId . '_' . $generation . '_' . $normalizedEngineType);
	}

	private function invalidateGeneratedCrlCache(string $instanceId, int $generation, string $engineType): void {
		$this->generatedCrlStorage->delete($instanceId, $generation, $engineType);
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
		return $this->resolveGeneratedCrlDer($instanceId, $generation, $engineType, false);
	}

	public function refreshGeneratedCrlDer(string $instanceId, int $generation, string $engineType): string {
		return $this->resolveGeneratedCrlDer($instanceId, $generation, $engineType, true);
	}

	private function resolveGeneratedCrlDer(string $instanceId, int $generation, string $engineType, bool $forceRefresh): string {
		$persistedCrl = $this->readPersistedGeneratedCrl($instanceId, $generation, $engineType);
		if (!$forceRefresh && $persistedCrl !== null && $persistedCrl['isFresh']) {
			return $persistedCrl['content'];
		}

		$lockKey = $this->generatedCrlCacheKey($instanceId, $generation, $engineType);
		$lockToken = bin2hex(random_bytes(16));
		$lockAcquired = $this->lockCache->add($lockKey, $lockToken, self::GENERATED_CRL_LOCK_TTL);

		if (!$lockAcquired) {
			$reloadedPersistedCrl = $this->readPersistedGeneratedCrl($instanceId, $generation, $engineType);
			if ($reloadedPersistedCrl !== null && ($reloadedPersistedCrl['isFresh'] || (!$forceRefresh && $reloadedPersistedCrl['isReusable']))) {
				return $reloadedPersistedCrl['content'];
			}

			if (!$forceRefresh && $persistedCrl !== null && $persistedCrl['isReusable']) {
				return $persistedCrl['content'];
			}

			$waitedPersistedCrl = $this->waitForPersistedGeneratedCrl($instanceId, $generation, $engineType);
			if ($waitedPersistedCrl !== null && ($waitedPersistedCrl['isFresh'] || (!$forceRefresh && $waitedPersistedCrl['isReusable']))) {
				return $waitedPersistedCrl['content'];
			}

			$lockAcquired = $this->lockCache->add($lockKey, $lockToken, self::GENERATED_CRL_LOCK_TTL);
			if (!$lockAcquired) {
				throw new \RuntimeException(sprintf(
					'CRL %s is already in progress for %s/%d/%s',
					$forceRefresh ? 'refresh' : 'generation',
					$instanceId,
					$generation,
					$engineType,
				));
			}
		}

		try {
			if (!$forceRefresh) {
				$reloadedPersistedCrl = $this->readPersistedGeneratedCrl($instanceId, $generation, $engineType);
				if ($reloadedPersistedCrl !== null && $reloadedPersistedCrl['isFresh']) {
					return $reloadedPersistedCrl['content'];
				}
			}

			return $this->buildAndPersistGeneratedCrlDer($instanceId, $generation, $engineType);
		} finally {
			$this->lockCache->cad($lockKey, $lockToken);
		}
	}

	/**
	 * @return array{content: string, isFresh: bool, isReusable: bool}|null
	 */
	private function waitForPersistedGeneratedCrl(string $instanceId, int $generation, string $engineType): ?array {
		for ($attempt = 0; $attempt < self::GENERATED_CRL_LOCK_WAIT_ATTEMPTS; $attempt++) {
			usleep(self::GENERATED_CRL_LOCK_WAIT_USEC);
			$persistedCrl = $this->readPersistedGeneratedCrl($instanceId, $generation, $engineType);
			if ($persistedCrl !== null && ($persistedCrl['isFresh'] || $persistedCrl['isReusable'])) {
				return $persistedCrl;
			}
		}

		return null;
	}

	/**
	 * @return array{content: string, isFresh: bool, isReusable: bool}|null
	 */
	private function readPersistedGeneratedCrl(string $instanceId, int $generation, string $engineType): ?array {
		$content = $this->generatedCrlStorage->read($instanceId, $generation, $engineType);
		if ($content === null) {
			return null;
		}

		$generatedAt = $this->getPersistedGeneratedCrlTimestamp($instanceId, $generation, $engineType);
		$isReusable = $generatedAt !== null
			&& $generatedAt >= (time() - self::GENERATED_CRL_MAX_STALE_AGE);

		return [
			'content' => $content,
			'isFresh' => $this->isPersistedGeneratedCrlFresh($instanceId, $generation, $engineType),
			'isReusable' => $isReusable,
		];
	}

	private function isPersistedGeneratedCrlFresh(string $instanceId, int $generation, string $engineType): bool {
		$metadata = $this->generatedCrlStorage->readMetadata($instanceId, $generation, $engineType);
		$refreshDate = $metadata['refreshDate'] ?? null;
		if (is_string($refreshDate) && $refreshDate !== '') {
			return $refreshDate === $this->getCurrentRefreshDate();
		}

		$generatedAt = $this->getPersistedGeneratedCrlTimestamp($instanceId, $generation, $engineType);
		if ($generatedAt === null) {
			return false;
		}

		return (new \DateTimeImmutable('@' . $generatedAt))
			->setTimezone(new \DateTimeZone(date_default_timezone_get()))
			->format(self::GENERATED_CRL_REFRESH_DATE_FORMAT) === $this->getCurrentRefreshDate();
	}

	private function getPersistedGeneratedCrlTimestamp(string $instanceId, int $generation, string $engineType): ?int {
		$metadata = $this->generatedCrlStorage->readMetadata($instanceId, $generation, $engineType);
		$generatedAt = $metadata['generatedAt'] ?? null;
		if (is_string($generatedAt) && $generatedAt !== '') {
			try {
				return (new \DateTimeImmutable($generatedAt))->getTimestamp();
			} catch (\Exception) {
				// Fall back to file mtime below.
			}
		}

		return $this->generatedCrlStorage->getMTime($instanceId, $generation, $engineType);
	}

	private function buildAndPersistGeneratedCrlDer(string $instanceId, int $generation, string $engineType): string {
		try {
			$crlDer = $this->buildCrlDer($instanceId, $generation, $engineType);
			$this->generatedCrlStorage->write(
				$instanceId,
				$generation,
				$engineType,
				$crlDer,
				[
					'refreshDate' => $this->getCurrentRefreshDate(),
					'generatedAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
					'engineType' => $this->normalizeEngineType($engineType)->value,
				]
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

	private function getCurrentRefreshDate(): string {
		return (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get())))
			->format(self::GENERATED_CRL_REFRESH_DATE_FORMAT);
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

		/** @var list<LibresignCrlListItem> $formattedData */
		$formattedData = array_values(array_map(
			fn (CrlEntity $entity): array => $this->formatCrlListEntry($entity),
			$result['data']
		));

		/** @var LibresignCrlListResponse $response */
		$response = [
			'data' => $formattedData,
			'total' => $result['total'],
			'page' => $page,
			'length' => $length,
		];

		return $response;
	}

	/**
	 * @return LibresignCrlListItem
	 */
	private function formatCrlListEntry(CrlEntity $entity): array {
		return [
			'id' => (int)$entity->getId(),
			'serial_number' => $entity->getSerialNumber(),
			'owner' => $entity->getOwner(),
			'status' => CRLStatus::from($entity->getStatus())->value,
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
		];
	}
}
