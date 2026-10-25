<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\Db\File as LibreSignFile;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\Worker\WorkerHealthService;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\Security\ICredentialsManager;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

class AsyncSigningService {
	public function __construct(
		private IJobList $jobList,
		private ICredentialsManager $credentialsManager,
		private ISecureRandom $secureRandom,
		private FileStatusService $fileStatusService,
		private IDBConnection $db,
		private WorkerHealthService $workerHealthService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return array{credentialsId: string, jobAdded: bool}
	 */
	public function enqueueSigningJob(
		LibreSignFile $libreSignFile,
		SignRequest $signRequest,
		?IUser $user,
		string $userUniqueIdentifier,
		bool $signWithoutPassword,
		?string $password,
		?string $signatureMethod,
		array $visibleElements,
		array $metadata,
	): array {
		// Both the job and the status belong to the same database transaction.
		// Without it the status may survive a failed enqueue, or a worker may
		// claim the job before its signing status has been persisted.
		$previousStatus = $libreSignFile->getStatus();
		$credentialsId = $this->storeCredentials($signRequest, $user, $signWithoutPassword, $password);
		$jobUserId = $user?->getUID() ?? $libreSignFile->getUserId();
		$transactionOpen = false;
		$statusUpdated = false;

		try {
			$this->db->beginTransaction();
			$transactionOpen = true;

			$libreSignFile->setStatus(FileStatus::SIGNING_IN_PROGRESS->value);
			$statusUpdated = true;
			$this->fileStatusService->updateForQueuedJob($libreSignFile);

			$this->jobList->add(SignFileJob::class, [
				'fileId' => $libreSignFile->getId(),
				'signRequestId' => $signRequest->getId(),
				'signRequestUuid' => $signRequest->getUuid(),
				'userId' => $jobUserId,
				'credentialsId' => $credentialsId,
				'isExternalSigner' => $user === null,
				'userUniqueIdentifier' => $userUniqueIdentifier,
				'friendlyName' => $signRequest->getDisplayName(),
				'signatureMethod' => $signatureMethod,
				'visibleElements' => $visibleElements,
				'metadata' => $metadata,
			]);

			$this->db->commit();
			$transactionOpen = false;
		} catch (\Throwable $e) {
			$rolledBack = !$transactionOpen;
			if ($transactionOpen) {
				try {
					$this->db->rollBack();
					$rolledBack = true;
				} catch (\Throwable) {
					// The commit may have succeeded. Do not overwrite its status or
					// delete the credentials of a potentially committed job.
				}
			}

			if ($rolledBack) {
				$libreSignFile->setStatus($previousStatus);
			}
			if ($statusUpdated && $rolledBack) {
				try {
					// Refresh status caches written before the transaction committed.
					$this->fileStatusService->update($libreSignFile);
				} catch (\Throwable) {
					// Do not hide the enqueue failure with a cache refresh failure.
				}
			}
			if ($rolledBack) {
				try {
					$this->credentialsManager->delete($user?->getUID() ?? '', $credentialsId);
				} catch (\Throwable) {
					// Credentials are never logged or exposed in the exception.
				}
			}
			throw $e;
		}

		try {
			$this->fileStatusService->publishQueuedJobStatus($libreSignFile);
		} catch (\Throwable $e) {
			// A cache failure after commit must not make the user enqueue the job again.
			$this->logger->warning('Unable to publish queued signing status', ['exception' => $e]);
		}

		// Worker launch is best effort. The job is already committed and can be
		// processed by the normal Nextcloud cron if local launch fails.
		$this->workerHealthService->ensureWorkerRunning();

		return [
			'credentialsId' => $credentialsId,
			'jobAdded' => true,
		];
	}

	private function storeCredentials(
		SignRequest $signRequest,
		?IUser $user,
		bool $signWithoutPassword,
		?string $password,
	): string {
		$credentialsId = 'libresign_sign_' . $signRequest->getId() . '_' . $this->secureRandom->generate(16, ISecureRandom::CHAR_ALPHANUMERIC);

		$this->credentialsManager->store(
			$user?->getUID() ?? '',
			$credentialsId,
			[
				'signWithoutPassword' => $signWithoutPassword,
				'password' => $password,
				'timestamp' => time(),
			]
		);

		return $credentialsId;
	}
}
