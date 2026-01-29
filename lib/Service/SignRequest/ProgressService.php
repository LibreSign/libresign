<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignRequest;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\ICache;
use OCP\ICacheFactory;

class ProgressService {
	private ICache $cache;
	public const ERROR_KEY_PREFIX = 'libresign_sign_request_error_';
	public const FILE_ERROR_KEY_PREFIX = 'libresign_file_error_';
	public const ERROR_CACHE_TTL = 300;
	/** @var array<string, array> */
	private array $signRequestErrors = [];
	/** @var array<string, array> */
	private array $fileErrors = [];

	public function __construct(
		private FileMapper $fileMapper,
		ICacheFactory $cacheFactory,
		private SignRequestMapper $signRequestMapper,
		private StatusCacheService $statusCacheService,
	) {
		$this->cache = $cacheFactory->createDistributed('libresign_progress');
	}

	/**
	 * Poll for status change of a sign request
	 *
	 * Waits up to the specified timeout for the status to change by checking cache
	 *
	 * @return int The current status (changed or original if timeout reached)
	 */
	public function pollForStatusChange(string $uuid, int $initialStatus, int $timeout = 30, int $intervalSeconds = 1): int {
		return $this->pollForStatusChangeInternal($uuid, [], $initialStatus, $timeout, $intervalSeconds);
	}

	public function pollForStatusOrErrorChange(
		FileEntity $file,
		SignRequestEntity $signRequest,
		int $initialStatus,
		int $timeout = 30,
		int $intervalSeconds = 1,
	): int {
		if ($file->getNodeType() !== 'envelope') {
			return $this->pollForProgressChange(
				$file,
				$signRequest,
				[$signRequest->getUuid()],
				$initialStatus,
				$timeout,
				$intervalSeconds,
			);
		}

		$signRequestUuids = [$signRequest->getUuid()];
		$childSignRequests = $this->signRequestMapper
			->getByEnvelopeChildrenAndIdentifyMethod($file->getId(), $signRequest->getId());
		foreach ($childSignRequests as $childSignRequest) {
			$childUuid = $childSignRequest->getUuid();
			if ($childUuid !== '') {
				$signRequestUuids[] = $childUuid;
			}
		}

		return $this->pollForProgressChange(
			$file,
			$signRequest,
			$signRequestUuids,
			$initialStatus,
			$timeout,
			$intervalSeconds,
		);
	}

	private function pollForProgressChange(
		FileEntity $file,
		SignRequestEntity $signRequest,
		array $errorUuids,
		int $initialStatus,
		int $timeout,
		int $intervalSeconds,
	): int {
		$statusUuid = $file->getUuid();
		$cachedStatus = $this->statusCacheService->getStatus($statusUuid);
		$interval = max(1, $intervalSeconds);
		$initialProgress = $this->getSignRequestProgress($file, $signRequest);
		$initialHash = $this->buildProgressHash($initialProgress);

		if ($cachedStatus !== false && $cachedStatus !== null && (int)$cachedStatus !== $initialStatus) {
			return (int)$cachedStatus;
		}

		for ($elapsed = 0; $elapsed < $timeout; $elapsed += $interval) {
			if (!empty($errorUuids) && $this->hasAnySignRequestError($errorUuids)) {
				return $initialStatus;
			}

			$newCachedStatus = $this->statusCacheService->getStatus($statusUuid);
			if ($newCachedStatus !== $cachedStatus && $newCachedStatus !== false && $newCachedStatus !== null) {
				return (int)$newCachedStatus;
			}

			$currentProgress = $this->getSignRequestProgress($file, $signRequest);
			$currentHash = $this->buildProgressHash($currentProgress);

			if ($currentHash !== $initialHash) {
				return $newCachedStatus !== false && $newCachedStatus !== null
					? (int)$newCachedStatus
					: $initialStatus;
			}

			if ($intervalSeconds > 0) {
				sleep($intervalSeconds);
			}
		}

		return $initialStatus;
	}

	private function pollForStatusChangeInternal(
		string $statusUuid,
		array $errorUuids,
		int $initialStatus,
		int $timeout,
		int $intervalSeconds,
	): int {
		$cachedStatus = $this->statusCacheService->getStatus($statusUuid);
		$interval = max(1, $intervalSeconds);

		if ($cachedStatus !== false && $cachedStatus !== null && (int)$cachedStatus !== $initialStatus) {
			return (int)$cachedStatus;
		}

		for ($elapsed = 0; $elapsed < $timeout; $elapsed += $interval) {
			if (!empty($errorUuids) && $this->hasAnySignRequestError($errorUuids)) {
				return $initialStatus;
			}

			$newCachedStatus = $this->statusCacheService->getStatus($statusUuid);
			if ($newCachedStatus !== $cachedStatus && $newCachedStatus !== false) {
				return (int)$newCachedStatus;
			}

			if ($intervalSeconds > 0) {
				sleep($intervalSeconds);
			}
		}

		return $initialStatus;
	}

	/**
	 * @param array<string, mixed> $progress
	 */
	private function buildProgressHash(array $progress): string {
		if (!empty($progress['files']) && is_array($progress['files'])) {
			usort($progress['files'], function (array $left, array $right): int {
				return ($left['id'] ?? 0) <=> ($right['id'] ?? 0);
			});
		}

		if (!empty($progress['signers']) && is_array($progress['signers'])) {
			usort($progress['signers'], function (array $left, array $right): int {
				return ($left['id'] ?? 0) <=> ($right['id'] ?? 0);
			});
		}

		return hash('sha256', json_encode($progress, JSON_UNESCAPED_SLASHES) ?: '');
	}

	public function setSignRequestError(string $uuid, array $error, int $ttl = self::ERROR_CACHE_TTL): void {
		$this->signRequestErrors[$uuid] = $error;
		$this->cache->set(self::ERROR_KEY_PREFIX . $uuid, $error, $ttl);
		$this->storeSignRequestErrorInMetadata($uuid, $error);
	}

	public function getSignRequestError(string $uuid): ?array {
		$error = $this->cache->get(self::ERROR_KEY_PREFIX . $uuid);
		if ($error === false || $error === null) {
			return $this->signRequestErrors[$uuid]
				?? $this->getSignRequestErrorFromMetadata($uuid);
		}
		return is_array($error) ? $error : ['message' => (string)$error];
	}

	public function clearSignRequestError(string $uuid): void {
		unset($this->signRequestErrors[$uuid]);
		$this->cache->remove(self::ERROR_KEY_PREFIX . $uuid);
		$this->clearSignRequestErrorInMetadata($uuid);
	}

	private function hasSignRequestError(string $uuid): bool {
		$error = $this->getSignRequestError($uuid);
		return $error !== null;
	}

	private function hasAnySignRequestError(array $uuids): bool {
		foreach ($uuids as $uuid) {
			if ($uuid !== '' && $this->hasSignRequestError($uuid)) {
				return true;
			}
		}
		return false;
	}

	public function setFileError(string $uuid, int $fileId, array $error, int $ttl = self::ERROR_CACHE_TTL): void {
		$key = $this->buildFileErrorKey($uuid, $fileId);
		$this->fileErrors[$key] = $error;
		$this->cache->set($key, $error, $ttl);
		$this->storeFileErrorInMetadata($uuid, $fileId, $error);
	}

	public function getFileError(string $uuid, int $fileId): ?array {
		$key = $this->buildFileErrorKey($uuid, $fileId);
		$error = $this->cache->get($key);
		if ($error === false || $error === null) {
			return $this->fileErrors[$key]
				?? $this->getFileErrorFromMetadata($uuid, $fileId);
		}
		return is_array($error) ? $error : ['message' => (string)$error];
	}

	public function clearFileError(string $uuid, int $fileId): void {
		$key = $this->buildFileErrorKey($uuid, $fileId);
		unset($this->fileErrors[$key]);
		$this->cache->remove($key);
		$this->clearFileErrorInMetadata($uuid, $fileId);
	}

	private function buildFileErrorKey(string $uuid, int $fileId): string {
		return self::FILE_ERROR_KEY_PREFIX . $uuid . '_' . $fileId;
	}

	private function storeSignRequestErrorInMetadata(string $uuid, array $error): void {
		if ($uuid === '') {
			return;
		}

		try {
			$signRequest = $this->signRequestMapper->getByUuidUncached($uuid);
		} catch (DoesNotExistException) {
			return;
		}
		if (!$signRequest instanceof SignRequestEntity) {
			return;
		}

		$metadata = $signRequest->getMetadata() ?? [];
		$metadata['libresign_error'] = $error;
		$signRequest->setMetadata($metadata);
		$this->signRequestMapper->update($signRequest);
	}

	private function getSignRequestErrorFromMetadata(string $uuid): ?array {
		if ($uuid === '') {
			return null;
		}

		try {
			$signRequest = $this->signRequestMapper->getByUuidUncached($uuid);
		} catch (DoesNotExistException) {
			return null;
		}
		if (!$signRequest instanceof SignRequestEntity) {
			return null;
		}

		$metadata = $signRequest->getMetadata() ?? [];
		$error = $metadata['libresign_error'] ?? null;
		return is_array($error) ? $error : null;
	}

	private function clearSignRequestErrorInMetadata(string $uuid): void {
		if ($uuid === '') {
			return;
		}

		try {
			$signRequest = $this->signRequestMapper->getByUuidUncached($uuid);
		} catch (DoesNotExistException) {
			return;
		}
		if (!$signRequest instanceof SignRequestEntity) {
			return;
		}

		$metadata = $signRequest->getMetadata() ?? [];
		if (!array_key_exists('libresign_error', $metadata)) {
			return;
		}

		unset($metadata['libresign_error']);
		$signRequest->setMetadata($metadata);
		$this->signRequestMapper->update($signRequest);
	}

	private function storeFileErrorInMetadata(string $uuid, int $fileId, array $error): void {
		if ($uuid === '') {
			return;
		}

		try {
			$signRequest = $this->signRequestMapper->getByUuidUncached($uuid);
		} catch (DoesNotExistException) {
			return;
		}
		if (!$signRequest instanceof SignRequestEntity) {
			return;
		}

		$metadata = $signRequest->getMetadata() ?? [];
		$fileErrors = $metadata['libresign_file_errors'] ?? [];
		if (!is_array($fileErrors)) {
			$fileErrors = [];
		}

		$fileErrors[$fileId] = $error;
		$metadata['libresign_file_errors'] = $fileErrors;
		$signRequest->setMetadata($metadata);
		$this->signRequestMapper->update($signRequest);
	}

	private function getFileErrorFromMetadata(string $uuid, int $fileId): ?array {
		if ($uuid === '') {
			return null;
		}

		try {
			$signRequest = $this->signRequestMapper->getByUuidUncached($uuid);
		} catch (DoesNotExistException) {
			return null;
		}
		if (!$signRequest instanceof SignRequestEntity) {
			return null;
		}

		$metadata = $signRequest->getMetadata() ?? [];
		$fileErrors = $metadata['libresign_file_errors'] ?? null;
		if (!is_array($fileErrors)) {
			return null;
		}

		$error = $fileErrors[$fileId] ?? null;
		return is_array($error) ? $error : null;
	}

	private function clearFileErrorInMetadata(string $uuid, int $fileId): void {
		if ($uuid === '') {
			return;
		}

		try {
			$signRequest = $this->signRequestMapper->getByUuidUncached($uuid);
		} catch (DoesNotExistException) {
			return;
		}
		if (!$signRequest instanceof SignRequestEntity) {
			return;
		}

		$metadata = $signRequest->getMetadata() ?? [];
		$fileErrors = $metadata['libresign_file_errors'] ?? null;
		if (!is_array($fileErrors) || !array_key_exists($fileId, $fileErrors)) {
			return;
		}

		unset($fileErrors[$fileId]);
		if (empty($fileErrors)) {
			unset($metadata['libresign_file_errors']);
		} else {
			$metadata['libresign_file_errors'] = $fileErrors;
		}
		$signRequest->setMetadata($metadata);
		$this->signRequestMapper->update($signRequest);
	}

	/**
	 * Get progress for a specific sign request
	 *
	 * Returns progress data tailored to the specific sign request,
	 * not the global file status
	 *
	 * @return array<string, mixed> Progress data with structure: {total, signed, pending, files?, signers?}
	 */
	public function getSignRequestProgress(FileEntity $file, SignRequestEntity $signRequest): array {
		return match (true) {
			$file->getNodeType() === 'envelope' => $this->getEnvelopeProgressForSignRequest($file, $signRequest),
			!$file->getParentFileId() => $this->getSingleFileProgressForSignRequest($file, $signRequest),
			default => $this->getFileProgressForSignRequest($file, $signRequest),
		};
	}

	public function getStatusCodeForSignRequest(FileEntity $file, SignRequestEntity $signRequest): int {
		return $this->getSignRequestStatusCode($file, $signRequest);
	}

	public function isProgressComplete(array $progress): bool {
		$total = (int)($progress['total'] ?? 0);
		if ($total <= 0) {
			return false;
		}
		$signed = (int)($progress['signed'] ?? 0);
		$pending = (int)($progress['pending'] ?? 0);
		$inProgress = (int)($progress['inProgress'] ?? 0);
		$errors = (int)($progress['errors'] ?? 0);
		return ($signed + $errors) >= $total && $pending <= 0 && $inProgress <= 0;
	}

	/**
	 * Get progress for a sign request on a single file
	 *
	 * Returns counts relative to the sign request status
	 *
	 * @return array<string, mixed>
	 */
	public function getSingleFileProgressForSignRequest(FileEntity $file, SignRequestEntity $signRequest): array {
		$statusCode = $this->getSignRequestStatusCode($file, $signRequest);
		$isSigned = $statusCode === FileStatus::SIGNED->value;
		$isInProgress = $statusCode === FileStatus::SIGNING_IN_PROGRESS->value;
		$fileError = $this->getFileError($signRequest->getUuid(), $file->getId());
		$hasError = $fileError !== null;

		return [
			'total' => 1,
			'signed' => $isSigned ? 1 : 0,
			'inProgress' => $hasError ? 0 : ($isInProgress ? 1 : 0),
			'errors' => $hasError ? 1 : 0,
			'pending' => $hasError || $isSigned || $isInProgress ? 0 : 1,
			'files' => [
				array_merge(
					[
						'id' => $file->getId(),
						'name' => $file->getName(),
						'status' => $statusCode,
						'statusText' => $this->fileMapper->getTextOfStatus($statusCode),
					],
					$hasError ? ['error' => $fileError] : []
				)
			],
		];
	}

	/**
	 * Get progress for a sign request on an envelope
	 *
	 * Returns progress for all files in the envelope relative to this signer
	 *
	 * @return array<string, mixed>
	 */
	public function getEnvelopeProgressForSignRequest(FileEntity $envelope, SignRequestEntity $signRequest): array {
		$children = $this->fileMapper->getChildrenFiles($envelope->getId());
		if (empty($children)) {
			$children = [$envelope];
		}

		$childSignRequests = $this->signRequestMapper
			->getByEnvelopeChildrenAndIdentifyMethod($envelope->getId(), $signRequest->getId());
		$childSignRequestsByFileId = [];
		foreach ($childSignRequests as $childSignRequest) {
			$childSignRequestsByFileId[$childSignRequest->getFileId()] = $childSignRequest;
		}

		$files = array_map(function (FileEntity $child) use ($signRequest, $childSignRequestsByFileId): array {
			$childSignRequest = $childSignRequestsByFileId[$child->getId()] ?? null;
			return $this->mapSignRequestFileProgressWithContext($child, $signRequest, $childSignRequest);
		}, $children);
		$total = count($files);
		$signed = count(array_filter($files, fn (array $file) => $file['status'] === FileStatus::SIGNED->value));
		$inProgress = count(array_filter($files, fn (array $file) => $file['status'] === FileStatus::SIGNING_IN_PROGRESS->value));
		$errors = count(array_filter($files, fn (array $file) => !empty($file['error'])));
		$pending = max(0, $total - $signed - $inProgress - $errors);

		return [
			'total' => $total,
			'signed' => $signed,
			'inProgress' => $inProgress,
			'errors' => $errors,
			'pending' => $pending,
			'files' => $files,
		];
	}

	/**
	 * Get progress for a sign request on a child file in an envelope
	 *
	 * @return array<string, mixed>
	 */
	public function getFileProgressForSignRequest(FileEntity $file, SignRequestEntity $signRequest): array {
		$statusCode = $this->getSignRequestStatusCode($file, $signRequest);
		$isSigned = $statusCode === FileStatus::SIGNED->value;
		$isInProgress = $statusCode === FileStatus::SIGNING_IN_PROGRESS->value;
		$fileError = $this->getFileError($signRequest->getUuid(), $file->getId());
		$hasError = $fileError !== null;

		return [
			'total' => 1,
			'signed' => $isSigned ? 1 : 0,
			'inProgress' => $hasError ? 0 : ($isInProgress ? 1 : 0),
			'errors' => $hasError ? 1 : 0,
			'pending' => $hasError || $isSigned || $isInProgress ? 0 : 1,
			'signers' => [
				[
					'id' => $signRequest->getId(),
					'displayName' => $signRequest->getDisplayName(),
					'signed' => $signRequest->getSigned()?->format('c'),
					'status' => $statusCode,
				]
			],
		];
	}

	/**
	 * Map file progress data
	 *
	 * @return array<string, mixed>
	 */
	private function mapFileProgress(FileEntity $file): array {
		return [
			'id' => $file->getId(),
			'name' => $file->getName(),
			'status' => $file->getStatus(),
			'statusText' => $this->fileMapper->getTextOfStatus($file->getStatus()),
		];
	}

	private function mapSignRequestFileProgress(FileEntity $file, SignRequestEntity $signRequest): array {
		$statusCode = $this->getSignRequestStatusCode($file, $signRequest);
		$error = $this->getFileError($signRequest->getUuid(), $file->getId());

		$mapped = [
			'id' => $file->getId(),
			'name' => $file->getName(),
			'status' => $statusCode,
			'statusText' => $this->fileMapper->getTextOfStatus($statusCode),
		];

		if ($error !== null) {
			$mapped['error'] = $error;
		}

		return $mapped;
	}

	private function mapSignRequestFileProgressWithContext(FileEntity $file, SignRequestEntity $defaultSignRequest, ?SignRequestEntity $childSignRequest): array {
		$effectiveSignRequest = $childSignRequest ?? $defaultSignRequest;
		$statusCode = $this->getSignRequestStatusCode($file, $effectiveSignRequest);
		$errorUuid = $childSignRequest?->getUuid() ?? $defaultSignRequest->getUuid();
		$error = $this->getFileError($errorUuid, $file->getId());
		if ($error === null) {
			$error = $this->findFileErrorAcrossSignRequests($file->getId());
		}

		$mapped = [
			'id' => $file->getId(),
			'name' => $file->getName(),
			'status' => $statusCode,
			'statusText' => $this->fileMapper->getTextOfStatus($statusCode),
		];

		if ($error !== null) {
			$mapped['error'] = $error;
		}

		return $mapped;
	}

	private function findFileErrorAcrossSignRequests(int $fileId): ?array {
		$signRequests = $this->signRequestMapper->getByFileId($fileId);
		foreach ($signRequests as $signRequest) {
			$error = $this->getFileError($signRequest->getUuid(), $fileId);
			if ($error !== null) {
				return $error;
			}
		}
		return null;
	}

	private function getSignRequestStatusCode(FileEntity $file, SignRequestEntity $signRequest): int {
		if ($signRequest->getSigned() !== null) {
			return FileStatus::SIGNED->value;
		}

		if ($file->getStatus() === FileStatus::SIGNING_IN_PROGRESS->value) {
			return FileStatus::SIGNING_IN_PROGRESS->value;
		}

		return match ($signRequest->getStatusEnum()) {
			SignRequestStatus::DRAFT => FileStatus::DRAFT->value,
			SignRequestStatus::ABLE_TO_SIGN => FileStatus::ABLE_TO_SIGN->value,
			default => $file->getStatus(),
		};
	}
}
