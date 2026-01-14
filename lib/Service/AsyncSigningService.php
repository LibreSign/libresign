<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\Db\File as LibreSignFile;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\FileStatus;
use OCP\BackgroundJob\IJobList;
use OCP\IUser;
use OCP\Security\ICredentialsManager;
use OCP\Security\ISecureRandom;

class AsyncSigningService {
	public function __construct(
		private IJobList $jobList,
		private ICredentialsManager $credentialsManager,
		private ISecureRandom $secureRandom,
		private FileMapper $fileMapper,
		private WorkerHealthService $workerHealthService,
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
		array $visibleElements,
		array $metadata,
	): array {
		$this->updateFileStatus($libreSignFile);
		$credentialsId = $this->storeCredentials($signRequest, $user, $signWithoutPassword, $password);

		$this->jobList->add(SignFileJob::class, [
			'fileId' => $libreSignFile->getId(),
			'signRequestId' => $signRequest->getId(),
			'userId' => $user?->getUID(),
			'credentialsId' => $credentialsId,
			'userUniqueIdentifier' => $userUniqueIdentifier,
			'friendlyName' => $signRequest->getDisplayName(),
			'visibleElements' => $visibleElements,
			'metadata' => $metadata,
		]);

		$this->workerHealthService->ensureWorkerRunning();

		return [
			'credentialsId' => $credentialsId,
			'jobAdded' => true,
		];
	}

	private function updateFileStatus(LibreSignFile $libreSignFile): void {
		$libreSignFile->setStatusEnum(FileStatus::SIGNING_IN_PROGRESS);
		$metadata = $libreSignFile->getMetadata() ?? [];
		$metadata['status_changed_at'] = (new \DateTime())->format(\DateTimeInterface::ATOM);
		$libreSignFile->setMetadata($metadata);
		$this->fileMapper->update($libreSignFile);
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
