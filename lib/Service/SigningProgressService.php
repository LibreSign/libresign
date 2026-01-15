<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use Psr\Log\LoggerInterface;

/**
 * Service to manage signing progress status
 *
 * Handles setting and reverting the SIGNING_IN_PROGRESS status
 * for files during the signing process.
 */
class SigningProgressService {
	public function __construct(
		private FileMapper $fileMapper,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Set SIGNING_IN_PROGRESS status for files before signing
	 *
	 * @param array $signRequests Sign requests with 'file' => FileEntity, 'signRequest' => SignRequestEntity
	 * @throws \Exception
	 */
	public function setInProgressStatus(array $signRequests): void {
		foreach ($signRequests as $signRequest) {
			$file = $signRequest['file'];
			$currentStatus = $file->getStatus();

			if ($currentStatus !== FileStatus::SIGNING_IN_PROGRESS->value) {
				$file->setStatusEnum(FileStatus::SIGNING_IN_PROGRESS);
				$this->fileMapper->update($file);
			}
		}
	}

	/**
	 * Revert SIGNING_IN_PROGRESS status back to ABLE_TO_SIGN on error
	 *
	 * @param FileEntity[] $files Files to revert status
	 * @param \Exception|null $exception Optional exception that caused the revert
	 * @throws \Exception
	 */
	public function revertInProgressStatus(array $files, ?\Exception $exception = null): void {
		foreach ($files as $file) {
			$currentStatus = $file->getStatus();

			if ($currentStatus === FileStatus::SIGNING_IN_PROGRESS->value) {
				$file->setStatusEnum(FileStatus::ABLE_TO_SIGN);
				$this->fileMapper->update($file);
			}
		}

		if ($exception !== null) {
			$this->logger->error('Failed to complete signing: {error}', [
				'error' => $exception->getMessage(),
				'exception' => $exception,
			]);
		}
	}
}
