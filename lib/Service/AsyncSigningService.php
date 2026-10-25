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
use OCA\Libresign\Service\Worker\WorkerActivationService;
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
		private WorkerActivationService $workerActivationService,
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
		$credentialsId = $this->storeCredentials($signRequest, $user, $signWithoutPassword, $password);
		$jobArguments = [
			'fileId' => $libreSignFile->getId(),
			'signRequestId' => $signRequest->getId(),
			'signRequestUuid' => $signRequest->getUuid(),
			'userId' => $user?->getUID() ?? $libreSignFile->getUserId(),
			'credentialsId' => $credentialsId,
			'isExternalSigner' => $user === null,
			'userUniqueIdentifier' => $userUniqueIdentifier,
			'friendlyName' => $signRequest->getDisplayName(),
			'signatureMethod' => $signatureMethod,
			'visibleElements' => $visibleElements,
			'metadata' => $metadata,
		];
		$this->persistQueuedJob($libreSignFile, $jobArguments, $user?->getUID() ?? '', $credentialsId);
		$this->invalidateQueuedStatus($libreSignFile);

		// The queued job is durable even if this best-effort launch fails.
		$this->workerActivationService->ensureWorkerRunning();
		return ['credentialsId' => $credentialsId, 'jobAdded' => true];
	}

	/** @param array<string, mixed> $jobArguments */
	private function persistQueuedJob(LibreSignFile $file, array $jobArguments, string $credentialOwner, string $credentialsId): void {
		$previousStatus = $file->getStatus();
		$previousMetadata = $file->getMetadata();
		$transactionOpen = false;
		try {
			// The job and its signing status must become visible together.
			$this->db->beginTransaction();
			$transactionOpen = true;
			$file->setStatus(FileStatus::SIGNING_IN_PROGRESS->value);
			$this->fileStatusService->updateForQueuedJob($file);
			$this->jobList->add(SignFileJob::class, $jobArguments);
			$this->db->commit();
		} catch (\Throwable $error) {
			// Compensate and rethrow even programming errors; never report that a
			// failed enqueue succeeded or discard a possibly committed job.
			$this->compensateFailedEnqueue($file, $previousStatus, $previousMetadata, $credentialOwner, $credentialsId, $transactionOpen);
			throw $error;
		}
	}

	private function compensateFailedEnqueue(
		LibreSignFile $file,
		int $previousStatus,
		?array $previousMetadata,
		string $credentialOwner,
		string $credentialsId,
		bool $transactionOpen,
	): void {
		if ($transactionOpen) {
			try {
				$this->db->rollBack();
			} catch (\Exception $e) {
				// After an ambiguous commit, these credentials may still be needed.
				$this->logger->error('Cannot roll back the signing enqueue', ['exception' => $e]);
				return;
			}
		}
		try {
			$this->fileStatusService->discardQueuedJobStatus($file, $previousStatus, $previousMetadata);
		} catch (\Exception $e) {
			$this->logger->warning('Cannot discard uncommitted signing status', ['exception' => $e]);
		}
		try {
			$this->credentialsManager->delete($credentialOwner, $credentialsId);
		} catch (\Exception $e) {
			$this->logger->warning('Cannot remove unused signing credentials', ['exception' => $e]);
		}
	}

	private function invalidateQueuedStatus(LibreSignFile $file): void {
		try {
			$this->fileStatusService->onQueuedJobCommitted($file);
		} catch (\Exception $e) {
			// A cache failure after commit must not initiate a second signing.
			$this->logger->warning('Unable to invalidate queued signing status', ['exception' => $e]);
		}
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
