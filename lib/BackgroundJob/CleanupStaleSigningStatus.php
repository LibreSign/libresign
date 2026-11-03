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
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Report stale signing operations for reconciliation.
 *
 * Keep the registered class name for upgrade compatibility. A timeout cannot
 * establish whether a cryptographic operation wrote a PDF, so resetting the
 * status automatically would permit an unsafe second signature.
 */
class CleanupStaleSigningStatus extends TimedJob {
	private const int STALE_TIMEOUT_MINUTES = 10;
	private const int MAX_FILE_IDS_IN_LOG = 10;

	public function __construct(
		ITimeFactory $time,
		private FileMapper $fileMapper,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
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

		// One diagnostic per cron run, not one warning for every affected file.
		$this->logger->warning('Stale signing statuses need manual review before retry', [
			'count' => count($staleFiles),
			'fileIds' => array_map(
				static fn (File $file): int => $file->getId(),
				array_slice($staleFiles, 0, self::MAX_FILE_IDS_IN_LOG),
			),
		]);
	}
}
