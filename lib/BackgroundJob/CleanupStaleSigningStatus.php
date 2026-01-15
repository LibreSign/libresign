<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\BackgroundJob;

use DateTime;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
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
	/** @var int Timeout in minutes - files in IN_PROGRESS for longer will be reverted */
	private const STALE_TIMEOUT_MINUTES = 10;

	public function __construct(
		ITimeFactory $time,
		private FileMapper $fileMapper,
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
		$staleThreshold = new DateTime();
		$staleThreshold->modify('-' . self::STALE_TIMEOUT_MINUTES . ' minutes');

		try {
			$staleFiles = $this->fileMapper->findStaleSigningInProgress($staleThreshold);

			$revertedCount = 0;
			foreach ($staleFiles as $file) {
				$file->setStatusEnum(FileStatus::ABLE_TO_SIGN);
				$meta = $file->getMetadata() ?? [];
				$meta['status_changed_at'] = (new DateTime())->format(DateTime::ATOM);
				$file->setMetadata($meta);
				$this->fileMapper->update($file);
				$revertedCount++;
			}
		} catch (\Exception $e) {
			$this->logger->error(
				'Failed to cleanup stale signing statuses: {message}',
				['message' => $e->getMessage(), 'exception' => $e]
			);
		}
	}
}
