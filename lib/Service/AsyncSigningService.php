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
use OCP\IUser;
use OCP\Security\ICredentialsManager;
use OCP\Security\ISecureRandom;

class AsyncSigningService {
	public function __construct(
		private IJobList $jobList,
		private ICredentialsManager $credentialsManager,
		private ISecureRandom $secureRandom,
		private FileStatusService $fileStatusService,
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
		?string $signatureMethod,
		array $visibleElements,
		array $metadata,
	): array {
		$libreSignFile->setStatus(FileStatus::SIGNING_IN_PROGRESS->value);
		$this->fileStatusService->update($libreSignFile);
		$credentialsId = $this->storeCredentials($signRequest, $user, $signWithoutPassword, $password);

		$this->jobList->add(SignFileJob::class, [
			'fileId' => $libreSignFile->getId(),
			'signRequestId' => $signRequest->getId(),
			'signRequestUuid' => $signRequest->getUuid(),
			'userId' => $user?->getUID(),
			'credentialsId' => $credentialsId,
			'userUniqueIdentifier' => $userUniqueIdentifier,
			'friendlyName' => $signRequest->getDisplayName(),
			'signatureMethod' => $signatureMethod,
			'visibleElements' => $visibleElements,
			'metadata' => $metadata,
		]);

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
