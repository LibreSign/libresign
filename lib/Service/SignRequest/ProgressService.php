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
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCP\ICache;
use OCP\ICacheFactory;

/**
 * Service for calculating and managing sign request progress
 *
 * This service encapsulates the business logic for:
 * - Calculating progress for specific sign requests
 * - Handling different file types (simple files, envelopes)
 * - Polling for status changes
 * - Building status responses
 *
 * Testable unit that can be tested independently of HTTP concerns
 */
class ProgressService {
	private ICache $cache;

	public function __construct(
		private FileMapper $fileMapper,
		ICacheFactory $cacheFactory,
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
		$cacheKey = 'status_' . $uuid;
		$cachedStatus = $this->cache->get($cacheKey);
		$interval = max(1, $intervalSeconds);

		for ($elapsed = 0; $elapsed < $timeout; $elapsed += $interval) {
			$newCachedStatus = $this->cache->get($cacheKey);

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
		return $signed >= $total && $pending <= 0 && $inProgress <= 0;
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

		return [
			'total' => 1,
			'signed' => $isSigned ? 1 : 0,
			'inProgress' => $isInProgress ? 1 : 0,
			'pending' => $isSigned || $isInProgress ? 0 : 1,
			'files' => [
				[
					'id' => $file->getId(),
					'name' => $file->getName(),
					'status' => $statusCode,
					'statusText' => $this->fileMapper->getTextOfStatus($statusCode),
				]
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

		$files = array_map(
			fn ($child) => $this->mapSignRequestFileProgress($child, $signRequest),
			$children
		);
		$total = count($files);
		$signed = count(array_filter($files, fn (array $file) => $file['status'] === FileStatus::SIGNED->value));
		$inProgress = count(array_filter($files, fn (array $file) => $file['status'] === FileStatus::SIGNING_IN_PROGRESS->value));
		$pending = max(0, $total - $signed - $inProgress);

		return [
			'total' => $total,
			'signed' => $signed,
			'inProgress' => $inProgress,
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

		return [
			'total' => 1,
			'signed' => $isSigned ? 1 : 0,
			'inProgress' => $isInProgress ? 1 : 0,
			'pending' => $isSigned || $isInProgress ? 0 : 1,
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
		return [
			'id' => $file->getId(),
			'name' => $file->getName(),
			'status' => $statusCode,
			'statusText' => $this->fileMapper->getTextOfStatus($statusCode),
		];
	}

	private function getSignRequestStatusCode(FileEntity $file, SignRequestEntity $signRequest): int {
		if ($file->getStatus() === FileStatus::SIGNING_IN_PROGRESS->value) {
			return FileStatus::SIGNING_IN_PROGRESS->value;
		}

		if ($signRequest->getSigned() !== null) {
			return FileStatus::SIGNED->value;
		}

		return match ($signRequest->getStatusEnum()) {
			SignRequestStatus::DRAFT => FileStatus::DRAFT->value,
			SignRequestStatus::ABLE_TO_SIGN => FileStatus::ABLE_TO_SIGN->value,
			default => $file->getStatus(),
		};
	}
}
