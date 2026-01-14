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
			$file = $this->fileMapper->getById($fileId);
			$statusChanged = $file->getStatus() !== $currentStatus;

			while (!$statusChanged && $elapsedTime < $timeout) {
				sleep(1);
				$elapsedTime++;

				$file = $this->fileMapper->getById($fileId);
				$statusChanged = $file->getStatus() !== $currentStatus;
			}

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
			$file = $this->fileMapper->getByUuid($uuid);
			$currentStatus = $file->getStatus();

			if ($currentStatus === FileStatus::SIGNING_IN_PROGRESS->value) {
				$this->workerHealthService->ensureWorkerRunning();
				$currentStatus = $this->pollForStatusChange($uuid, $currentStatus, $timeout);
			}

			return $this->buildStatusResponse($file, $currentStatus);

		} catch (\Exception $e) {
			return new DataResponse([
				'message' => $e->getMessage(),
				'status' => 'ERROR',
			], Http::STATUS_NOT_FOUND);
		}
	}

	private function getSigningProgress(FileEntity $file): array {
		if ($file->getNodeType() === 'envelope') {
			return $this->getEnvelopeProgress($file);
		}
		if (!$file->getParentFileId()) {
			return $this->getSingleFileProgress($file);
		}
		return $this->getFileProgress($file);
	}

	private function pollForStatusChange(string $uuid, int $initialStatus, int $timeout): int {
		$elapsedTime = 0;
		$cacheKey = 'status_' . $uuid;
		$cachedStatus = $this->cache->get($cacheKey);
		$currentStatus = $initialStatus;

		while ($elapsedTime < $timeout) {
			$newCachedStatus = $this->cache->get($cacheKey);

			if ($newCachedStatus !== $cachedStatus && $newCachedStatus !== false) {
				return (int)$newCachedStatus;
			}

			sleep(1);
			$elapsedTime++;
		}

		return $currentStatus;
	}

	private function buildStatusResponse(FileEntity $file, int $status): DataResponse {
		$statusEnum = FileStatus::tryFrom($status);

		return new DataResponse([
			'status' => $statusEnum?->name ?? 'UNKNOWN',
			'statusCode' => $status,
			'statusText' => $this->fileMapper->getTextOfStatus($status),
			'fileId' => $file->getId(),
			'progress' => $this->getSigningProgress($file),
		], Http::STATUS_OK);
	}

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
		if (empty($children)) {
			$children = [$envelope];
		}

		$totals = $this->countStatusTotals($children);
		$files = array_map(fn ($child) => $this->mapFileProgress($child), $children);

		return $totals + ['files' => $files];
	}

	private function countStatusTotals(array $children): array {
		$totals = ['total' => count($children), 'signed' => 0, 'inProgress' => 0, 'pending' => 0];

		foreach ($children as $child) {
			match ($child->getStatus()) {
				FileStatus::SIGNED->value => $totals['signed']++,
				FileStatus::SIGNING_IN_PROGRESS->value => $totals['inProgress']++,
				default => $totals['pending']++,
			};
		}

		return $totals;
	}

	private function mapFileProgress(FileEntity $file): array {
		return [
			'id' => $file->getId(),
			'name' => $file->getName(),
			'status' => $file->getStatus(),
			'statusText' => $this->fileMapper->getTextOfStatus($file->getStatus()),
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
