<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\BackgroundJob;

use DateTime;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\SigningAttemptGuard;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Background job to clean up stale SIGNING_IN_PROGRESS statuses
 *
 * If a signing process is interrupted (server crash, PHP timeout, etc.),
 * files can get stuck in SIGNING_IN_PROGRESS status. This job reverts
 * those statuses back to ABLE_TO_SIGN after a timeout period.
 */
class CleanupStaleSigningStatus extends TimedJob {
	private const int STALE_TIMEOUT_MINUTES = 10;
	private const int MAX_QUEUE_INSPECTION = 5000;

	public function __construct(
		ITimeFactory $time,
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private FileStatusService $fileStatusService,
		private SigningAttemptGuard $attemptGuard,
		private IJobList $jobList,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);

		// Run every 5 minutes
		$this->setInterval(60 * 5);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	#[\Override]
	public function run($argument): void {
		$staleFiles = $this->fileMapper->findStaleSigningInProgress(
			new DateTime('-' . self::STALE_TIMEOUT_MINUTES . ' minutes'),
		);
		if ($staleFiles === []) {
			return;
		}

		$pending = $this->getPendingSigningFileIds();
		if ($pending === null) {
			return;
		}

		foreach ($staleFiles as $file) {
			if (!$this->canResetStatus($file, $pending)) {
				continue;
			}
			if ($file->isEnvelope()) {
				$this->fileStatusService->propagateStatusToParent($file->getId());
			} else {
				$file->setStatusEnum(FileStatus::ABLE_TO_SIGN);
				$this->fileStatusService->update($file);
			}
		}
	}

	/** @param array<int, true> $pending */
	private function canResetStatus(File $file, array $pending): bool {
		if ($file->getSignedHash() !== null || $file->getSignedNodeId() !== null) {
			return false;
		}

		$finished = false;
		foreach ($this->relatedFileIds($file) as $fileId) {
			if (isset($pending[$fileId])) {
				return false;
			}
			foreach ($this->signRequestMapper->getByFileId($fileId) as $request) {
				if ($request->getSignedHash() !== null || $request->getSigned() !== null
					|| $this->attemptGuard->hasUncertainAttempt($request->getId())) {
					return false;
				}
				$finished = $this->attemptGuard->hasFinishedAttempt($request->getId()) || $finished;
			}
		}

		if (!$finished) {
			// Old QueuedJob jobs removed their queue entry before signing. A
			// timeout does not prove that the PDF operation is safe to retry.
			$this->logger->warning('Stale signing status requires manual review', ['fileId' => $file->getId()]);
		}
		return $finished;
	}

	/** @return list<int> */
	private function relatedFileIds(File $file): array {
		$ids = [$file->getId()];
		$parentId = $file->getParentFileId();
		if ($file->hasParent() && $parentId !== null) {
			$ids[] = $parentId;
		}
		if ($file->isEnvelope()) {
			foreach ($this->fileMapper->getChildrenFiles($file->getId()) as $child) {
				$childId = $child->getId();
				if ($childId !== null) {
					$ids[] = $childId;
				}
			}
		}
		return $ids;
	}

	/** @return array<int, true>|null Null means the queue could not be inspected completely. */
	private function getPendingSigningFileIds(): ?array {
		$fileIds = [];
		foreach ([SignFileJob::class, SignSingleFileJob::class] as $class) {
			$scanned = 0;
			foreach ($this->jobList->getJobsIterator($class, self::MAX_QUEUE_INSPECTION + 1, 0) as $job) {
				$scanned++;
				if ($scanned > self::MAX_QUEUE_INSPECTION) {
					$this->logger->warning('Signing queue is too large for safe stale cleanup');
					return null;
				}
				$args = $job->getArgument();
				$id = is_array($args) ? filter_var($args['fileId'] ?? null, FILTER_VALIDATE_INT) : false;
				if ($id === false || $id <= 0) {
					$this->logger->warning('Cannot inspect invalid signing job arguments safely');
					return null;
				}
				$fileIds[$id] = true;
			}
		}
		return $fileIds;
	}
}
