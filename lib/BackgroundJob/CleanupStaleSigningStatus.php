<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\BackgroundJob;

use DateTime;
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

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function run($argument): void {
		$staleThreshold = new DateTime('-' . self::STALE_TIMEOUT_MINUTES . ' minutes');

		try {
			$staleFiles = $this->fileMapper->findStaleSigningInProgress($staleThreshold);
			if ($staleFiles === []) {
				return;
			}
			$pending = $this->getPendingSigningFileIds();
			if ($pending === null) {
				// Could not inspect the whole queue safely. Never guess it is empty.
				return;
			}

			foreach ($staleFiles as $file) {
				$fileIds = [$file->getId()];
				if ($file->hasParent()) {
					// Sequential signing can run in a parent job while this child
					// has no queued job of its own.
					$fileIds[] = $file->getParentFileId();
				}
				if ($file->isEnvelope()) {
					foreach ($this->fileMapper->getChildrenFiles($file->getId()) as $child) {
						$fileIds[] = $child->getId();
					}
				}
				$pendingForFile = false;
				$uncertain = false;
				$finished = false;
				$signedOutput = $file->getSignedHash() !== null || $file->getSignedNodeId() !== null;
				foreach ($fileIds as $fileId) {
					if (isset($pending[$fileId])) {
						$pendingForFile = true;
						break;
					}
					foreach ($this->signRequestMapper->getByFileId($fileId) as $signRequest) {
						$signedOutput = $signedOutput || $signRequest->getSignedHash() !== null || $signRequest->getSigned() !== null;
						if ($this->attemptGuard->hasUncertainAttempt($signRequest->getId())) {
							$uncertain = true;
							break;
						}
						$finished = $finished || $this->attemptGuard->hasFinishedAttempt($signRequest->getId());
					}
					if ($uncertain) {
						break;
					}
				}
				if ($pendingForFile || $uncertain) {
					if ($uncertain) {
						$this->logger->warning('Signing needs review before retry', ['fileId' => $file->getId()]);
					}
					continue;
				}
				if (!$finished) {
					// Legacy QueuedJob removed its row before signing. Age alone
					// cannot prove the process ended or its PDF side effects are safe.
					$this->logger->warning('Stale signing status has no completion receipt; manual review required', ['fileId' => $file->getId()]);
					continue;
				}
				if ($signedOutput) {
					$this->logger->warning('Signed file or request has a stale processing status', ['fileId' => $file->getId()]);
					continue;
				}
				if ($file->isEnvelope()) {
					$this->fileStatusService->propagateStatusToParent($file->getId());
				} else {
					$file->setStatusEnum(FileStatus::ABLE_TO_SIGN);
					$this->fileStatusService->update($file);
				}
			}
		} catch (\Throwable $e) {
			$this->logger->error('Failed to clean up stale signing statuses', ['exception' => $e]);
		}
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
				if (!is_array($args) || !isset($args['fileId']) || !is_numeric($args['fileId'])) {
					return null;
				}
				$fileIds[(int)$args['fileId']] = true;
			}
		}
		return $fileIds;
	}
}
