<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use DateTime;
use DateTimeInterface;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ICredentialsManager;
use Psr\Log\LoggerInterface;

class SignJobCoordinator {
	public function __construct(
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private SignFileService $signFileService,
		private FolderService $folderService,
		private IUserManager $userManager,
		private ICredentialsManager $credentialsManager,
		private LoggerInterface $logger,
	) {
	}

	public function runSignFile(array $argument): void {
		try {
			if (empty($argument)) {
				throw new \InvalidArgumentException('SignFileJob: Cannot proceed with empty arguments');
			}

			[$fileId, $signRequestId] = $this->requireIds($argument, 'SignFileJob');
			[$file, $signRequest] = $this->loadEntities($fileId, $signRequestId);
			$user = $this->resolveUser($argument['userId'] ?? null, null, null);
			if ($user) {
				$this->folderService->setUserId($user->getUID());
			}

			$credentials = $this->retrieveCredentials($user, $argument['credentialsId'] ?? null);
			$this->hydrateSignService($argument, $credentials, $file, $signRequest, $user);

			$this->signFileService->sign();
		} catch (\Throwable $e) {
			$this->logger->error('SignFileJob failed: {error}', [
				'error' => $e->getMessage(),
				'exception' => $e,
			]);
		} finally {
			$this->deleteCredentials($argument['userId'] ?? '', $argument['credentialsId'] ?? null);
		}
	}

	public function runSignSingleFile(array $argument): void {
		$fileId = $argument['fileId'] ?? null;
		$signRequestId = $argument['signRequestId'] ?? null;
		$effectiveUserId = $argument['userId'] ?? null;
		$file = null;
		$signRequest = null;

		try {
			if (empty($argument)) {
				throw new \InvalidArgumentException('SignSingleFileJob: Cannot proceed with empty arguments');
			}

			[$fileId, $signRequestId] = $this->requireIds($argument, 'SignSingleFileJob');
			[$file, $signRequest] = $this->loadEntities($fileId, $signRequestId);


			$effectiveUserId = $effectiveUserId
				?? $file->getUserId()
				?? ($signRequest->getUserId() ?? null);
			$user = $this->resolveUser($effectiveUserId);
			if ($user) {
				$this->folderService->setUserId($user->getUID());
			}

			$credentials = $this->retrieveCredentials($user, $argument['credentialsId'] ?? null);
			$this->hydrateSignService($argument, $credentials, $file, $signRequest, $user);

			$this->markInProgress($file);

			$this->signFileService->signSingleFile($file, $signRequest);
		} catch (\Throwable $e) {
			$contextFileId = $fileId ?? ($argument['fileId'] ?? 'unknown');
			$this->logger->error('SignSingleFileJob failed for file {fileId}: {error}', [
				'fileId' => $contextFileId,
				'error' => $e->getMessage(),
				'exception' => $e,
			]);
		} finally {
			$this->deleteCredentials($argument['userId'] ?? ($effectiveUserId ?? ''), $argument['credentialsId'] ?? null);
		}
	}

	/**
	 * @return array{0:int,1:int}
	 */
	private function requireIds(array $argument, string $jobName): array {
		$fileId = $argument['fileId'] ?? null;
		$signRequestId = $argument['signRequestId'] ?? null;
		if ($fileId === null || $signRequestId === null) {
			$this->logger->error($jobName . ': Background job missing required arguments', [
				'jobName' => $jobName,
				'hasFileId' => $fileId !== null,
				'hasSignRequestId' => $signRequestId !== null,
				'argumentKeys' => array_keys($argument),
				'argumentCount' => count($argument),
			]);
			throw new \InvalidArgumentException($jobName . ': Missing fileId or signRequestId');
		}
		return [$fileId, $signRequestId];
	}

	/**
	 * @return array{0:FileEntity,1:SignRequestEntity}
	 */
	private function loadEntities(int $fileId, int $signRequestId): array {
		$file = $this->fileMapper->getById($fileId);
		$signRequest = $this->signRequestMapper->getById($signRequestId);
		return [$file, $signRequest];
	}

	private function resolveUser(?string $userId): ?IUser {
		if (empty($userId)) {
			return null;
		}
		return $this->userManager->get($userId);
	}

	private function retrieveCredentials(?IUser $user, ?string $credentialsId): ?array {
		if (empty($credentialsId) || $user === null) {
			return null;
		}
		return $this->credentialsManager->retrieve($user->getUID(), $credentialsId);
	}

	private function hydrateSignService(array $argument, ?array $credentials, FileEntity $file, SignRequestEntity $signRequest, ?IUser $user): void {
		if ($credentials && !empty($credentials['signWithoutPassword'])) {
			$this->signFileService->setSignWithoutPassword(true);
		} elseif ($credentials && !empty($credentials['password'])) {
			$this->signFileService->setPassword($credentials['password']);
		}

		if (!empty($argument['userUniqueIdentifier'])) {
			$this->signFileService->setUserUniqueIdentifier($argument['userUniqueIdentifier']);
		}

		if (!empty($argument['friendlyName'])) {
			$this->signFileService->setFriendlyName($argument['friendlyName']);
		}

		if (!empty($argument['signatureMethod'])) {
			$this->signFileService->setSignatureMethod($argument['signatureMethod']);
		}

		$this->signFileService
			->setLibreSignFile($file)
			->setSignRequest($signRequest)
			->setCurrentUser($user)
			->storeUserMetadata($argument['metadata'] ?? [])
			->setVisibleElements($argument['visibleElements'] ?? []);
	}

	private function markInProgress(FileEntity $file): void {
		$statusBefore = $file->getStatus();
		if ($statusBefore === FileStatus::SIGNING_IN_PROGRESS->value) {
			return;
		}

		$file->setStatusEnum(FileStatus::SIGNING_IN_PROGRESS);
		$meta = $file->getMetadata() ?? [];
		$meta['status_changed_at'] = (new DateTime())->format(DateTimeInterface::ATOM);
		$file->setMetadata($meta);
		$this->fileMapper->update($file);
	}

	private function deleteCredentials(string $userId, ?string $credentialsId): void {
		if (empty($credentialsId)) {
			return;
		}
		$this->credentialsManager->delete($userId, $credentialsId);
	}
}
