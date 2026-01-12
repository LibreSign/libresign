<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\WorkerHealthService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class FileProgressController extends OCSController {
	private ICache $cache;

	public function __construct(
		string $appName,
		IRequest $request,
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private WorkerHealthService $workerHealthService,
		private LoggerInterface $logger,
		ICacheFactory $cacheFactory,
	) {
		parent::__construct($appName, $request);
		$this->cache = $cacheFactory->createDistributed('libresign_progress');
	}

	/**
	 * Wait for file/envelope status changes (long polling)
	 *
	 * Keeps connection open for up to 30 seconds waiting for status change.
	 *
	 * @param int $fileId LibreSign file ID
	 * @param int $currentStatus Current status known by client
	 * @param int $timeout Seconds to wait (default 30, max 30)
	 * @return DataResponse<Http::STATUS_OK, array{status: int, statusText: string, name: string, progress: array<string, mixed>}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Status and progress returned
	 * 404: File not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/file/{fileId}/wait-status', requirements: ['apiVersion' => '(v1)'])]
	public function waitForStatusChange(
		int $fileId,
		int $currentStatus,
		int $timeout = 30,
	): DataResponse {
		$timeout = min(30, $timeout);
		$elapsedTime = 0;

		try {
			// Initial check
			$file = $this->fileMapper->getById($fileId);
			$statusChanged = $file->getStatus() !== $currentStatus;

			// Long polling loop
			while (!$statusChanged && $elapsedTime < $timeout) {
				sleep(1);
				$elapsedTime++;

				// Re-fetch from DB
				$file = $this->fileMapper->getById($fileId);
				$statusChanged = $file->getStatus() !== $currentStatus;
			}

			// Build response with progress data
			return new DataResponse([
				'status' => $file->getStatus(),
				'statusText' => $this->fileMapper->getTextOfStatus($file->getStatus()),
				'name' => $file->getName(),
				'progress' => $this->getSigningProgress($file),
			], Http::STATUS_OK);

		} catch (\Exception $e) {
			return new DataResponse([
				'message' => $e->getMessage(),
			], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Check file progress by UUID with long-polling (similar to Talk)
	 *
	 * Waits up to 30 seconds for status change using cache for efficiency.
	 *
	 * @param string $uuid File UUID
	 * @param int $timeout Maximum seconds to wait (default 30)
	 * @return DataResponse<Http::STATUS_OK, array{status: string, statusCode: int, statusText: string, fileId: int, progress: array<string, mixed>}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string, status: string}, array{}>
	 *
	 * 200: Status and progress returned
	 * 404: File not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/file/progress/{uuid}', requirements: ['apiVersion' => '(v1)'])]
	public function checkProgressByUuid(string $uuid, int $timeout = 30): DataResponse {
		try {
			// Get file by UUID
			$file = $this->fileMapper->getByUuid($uuid);
			$currentStatus = $file->getStatus();
			$progress = $this->getSigningProgress($file);

			// If we're in async progress status, ensure a worker is running
			if ($currentStatus === FileStatus::SIGNING_IN_PROGRESS->value) {
				$this->workerHealthService->ensureWorkerRunning();
			}

			$statusEnum = FileStatus::tryFrom($currentStatus);
			$statusText = $statusEnum?->name ?? 'UNKNOWN';

			// If already in final state, return immediately
			if ($currentStatus === FileStatus::SIGNED->value
				|| $currentStatus === FileStatus::DELETED->value) {
				return new DataResponse([
					'status' => $statusText,
					'statusCode' => $currentStatus,
					'statusText' => $this->fileMapper->getTextOfStatus($currentStatus),
					'fileId' => $file->getId(),
					'progress' => $progress,
				], Http::STATUS_OK);
			}

			// If not in signing state and not final, return without polling
			if ($currentStatus !== FileStatus::SIGNING_IN_PROGRESS->value) {
				return new DataResponse([
					'status' => $statusText,
					'statusCode' => $currentStatus,
					'statusText' => $this->fileMapper->getTextOfStatus($currentStatus),
					'fileId' => $file->getId(),
					'progress' => $progress,
				], Http::STATUS_OK);
			}

			// Only perform long-polling if currently SIGNING_IN_PROGRESS
			$elapsedTime = 0;
			$cacheKey = 'status_' . $uuid;

			// Get initial cached status
			$cachedStatus = $this->cache->get($cacheKey);

			while ($elapsedTime < $timeout) {
				// Check cache first (like Talk's checkCacheOrDatabase)
				$newCachedStatus = $this->cache->get($cacheKey);

				if ($newCachedStatus !== $cachedStatus && $newCachedStatus !== false) {
					// Cache changed - trust cache immediately to avoid DB lag
					$currentStatus = (int)$newCachedStatus;
					$statusEnum = FileStatus::tryFrom($currentStatus);
					$statusText = $statusEnum?->name ?? 'UNKNOWN';
					break;
				}

				// No cache change yet - wait 1 second
				sleep(1);
				$elapsedTime++;
			}

			return new DataResponse([
				'status' => $statusText,
				'statusCode' => $currentStatus,
				'statusText' => $this->fileMapper->getTextOfStatus($currentStatus),
				'fileId' => $file->getId(),
				'progress' => $this->getSigningProgress($file),
			], Http::STATUS_OK);

		} catch (\Exception $e) {
			return new DataResponse([
				'message' => $e->getMessage(),
				'status' => 'ERROR',
			], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Get signing progress for file or envelope
	 */
	private function getSigningProgress(FileEntity $file): array {
		if ($file->getNodeType() === 'envelope') {
			return $this->getEnvelopeProgress($file);
		}

		// For single files (no parent, not envelope), display as single-file progress
		// This covers async signing without relying on status checks which may lag
		if (!$file->getParentFileId()) {
			return $this->getSingleFileProgress($file);
		}

		return $this->getFileProgress($file);
	}

	/**
	 * Get progress for a single file (not an envelope, not a child)
	 * Returns file-list format for consistent UI display
	 */
	private function getSingleFileProgress(FileEntity $file): array {
		return [
			'total' => 1,
			'signed' => $file->getStatus() === FileStatus::SIGNED->value ? 1 : 0,
			'inProgress' => $file->getStatus() === FileStatus::SIGNING_IN_PROGRESS->value ? 1 : 0,
			'pending' => $file->getStatus() === FileStatus::SIGNING_IN_PROGRESS->value ? 0 : ($file->getStatus() === FileStatus::SIGNED->value ? 0 : 1),
			'files' => [
				[
					'id' => $file->getId(),
					'name' => $file->getName(),
					'status' => $file->getStatus(),
					'statusText' => $this->fileMapper->getTextOfStatus($file->getStatus()),
				]
			],
		];
	}

	private function getEnvelopeProgress(FileEntity $envelope): array {
		$children = $this->fileMapper->getChildrenFiles($envelope->getId());

		// If no children, this is an async-signing single file marked as envelope
		// Include the envelope itself in the progress display
		if (empty($children)) {
			$children = [$envelope];
		}

		$total = count($children);
		$signed = 0;
		$inProgress = 0;
		$pending = 0;

		$files = [];
		foreach ($children as $child) {
			$childStatus = $child->getStatus();
			if ($childStatus === FileStatus::SIGNED->value) {
				$signed++;
			} elseif ($childStatus === FileStatus::SIGNING_IN_PROGRESS->value) {
				$inProgress++;
			} else {
				// Any status other than SIGNED or SIGNING_IN_PROGRESS is pending
				// This includes: DRAFT, ABLE_TO_SIGN, PARTIAL_SIGNED, etc.
				$pending++;
			}

			$files[] = [
				'id' => $child->getId(),
				'name' => $child->getName(),
				'status' => $childStatus,
				'statusText' => $this->fileMapper->getTextOfStatus($childStatus),
			];
		}

		return [
			'total' => $total,
			'signed' => $signed,
			'inProgress' => $inProgress,
			'pending' => $pending,
			'files' => $files,
		];
	}

	private function getFileProgress(FileEntity $file): array {
		$signRequests = $this->signRequestMapper->getByFileId($file->getId());

		$total = count($signRequests);
		$signed = count(array_filter($signRequests, fn ($sr) => $sr->getSigned() !== null));

		return [
			'total' => $total,
			'signed' => $signed,
			'pending' => $total - $signed,
			'signers' => array_map(function ($sr) {
				return [
					'id' => $sr->getId(),
					'displayName' => $sr->getDisplayName(),
					'signed' => $sr->getSigned() ? $sr->getSigned()->format('c') : null,
					'status' => $sr->getStatus(),
				];
			}, $signRequests),
		];
	}
}
