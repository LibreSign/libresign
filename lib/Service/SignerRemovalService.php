<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Events\SignRequestCanceledEvent;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\SignRequest\SignRequestService;
use OCP\AppFramework\Db\TTransactional;
use OCP\AppFramework\Http;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * Coordinates signer removal without changing completed signing evidence.
 *
 * Database changes are atomic. Notifications and cache invalidation are kept
 * outside the transaction because neither can be rolled back with the database.
 */
class SignerRemovalService {
	use TTransactional;

	public function __construct(
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private IdentifyMethodService $identifyMethodService,
		private SignRequestService $signRequestService,
		private FileElementMapper $fileElementMapper,
		private SequentialSigningService $sequentialSigningService,
		private FileStatusService $fileStatusService,
		private IEventDispatcher $eventDispatcher,
		private IL10N $l10n,
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function remove(int $fileId, int $signRequestId, bool $notify = true): void {
		/** @var array<int, FileEntity> $affectedFiles */
		$affectedFiles = [];
		/** @var array<int, SignRequestEntity> $affectedSigners */
		$affectedSigners = [];
		/** @var list<SignRequestEntity> $activatedSigners */
		$activatedSigners = [];
		$committed = false;

		try {
			[$file, $signRequest, $identifyMethods] = $this->atomic(
				function () use ($fileId, $signRequestId, &$affectedFiles, &$affectedSigners, &$activatedSigners): array {
					$file = $this->fileMapper->getById($fileId);
					$signRequest = $this->signRequestMapper->getByFileIdAndSignRequestId($fileId, $signRequestId);
					$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequestId);
					$removals = $this->getRemovalTargets($file, $signRequest, $identifyMethods);

					foreach ($removals as [$affectedFile, $affectedSignRequest]) {
						$this->assertCanRemove($affectedFile, $affectedSignRequest);
						$affectedFiles[$affectedFile->getId()] = $affectedFile;
						$affectedSigners[$affectedSignRequest->getId()] = $affectedSignRequest;
						if ($affectedFile->hasParent()) {
							$parent = $this->fileMapper->getById($affectedFile->getParentFileId());
							$affectedFiles[$parent->getId()] = $parent;
						}
					}

					foreach ($removals as [$affectedFile, $affectedSignRequest]) {
						// The SQL predicate closes the race with a signing request that
						// becomes completed after the earlier read.
						if (!$this->signRequestMapper->deleteIfUnsigned($affectedSignRequest)) {
							$this->rejectRemoval();
						}

						$this->identifyMethodService->deleteBySignRequestId($affectedSignRequest->getId());
						foreach ($this->fileElementMapper->getByFileIdAndSignRequestId($affectedFile->getId(), $affectedSignRequest->getId()) as $element) {
							$this->fileElementMapper->delete($element);
						}

						$this->sequentialSigningService->setFile($affectedFile);
						foreach ($this->sequentialSigningService->activateAfterDeletion($affectedFile->getId(), $affectedSignRequest->getSigningOrder()) as $activated) {
							$affectedSigners[$activated->getId()] = $activated;
							$activatedSigners[] = $activated;
						}
						$this->revertToDraftWhenEmpty($affectedFile);
					}

					return [$file, $signRequest, $identifyMethods];
				},
				$this->db,
			);
			$committed = true;
		} finally {
			// Mappers and status services publish caches independently of a
			// database transaction. Always discard entries written before commit
			// or rollback, including parent envelopes and activated signers.
			foreach ($affectedSigners as $affectedSigner) {
				$this->signRequestMapper->invalidateCachedEntity($affectedSigner);
			}
			foreach ($affectedFiles as $affectedFile) {
				$this->fileMapper->invalidateCachedEntity($affectedFile);
				if ($committed) {
					$this->fileStatusService->cacheCurrentStatus($this->fileMapper->getById($affectedFile->getId()));
				} else {
					$this->fileStatusService->invalidateCachedStatus($affectedFile);
				}
			}
			$this->identifyMethodService->clearCache();
		}

		$this->sequentialSigningService->notifyActivatedSigners($activatedSigners);
		if ($notify && $signRequest->getStatus() === SignRequestStatus::ABLE_TO_SIGN->value) {
			$this->notifyCancellation($file, $signRequest, $identifyMethods);
		}
	}

	/**
	 * @param array<string, array<IIdentifyMethod>> $identifyMethods
	 * @return list<array{FileEntity, SignRequestEntity}>
	 */
	private function getRemovalTargets(FileEntity $file, SignRequestEntity $signRequest, array $identifyMethods): array {
		$targets = [[$file, $signRequest]];
		if (!$file->isEnvelope()) {
			return $targets;
		}

		$firstGroup = reset($identifyMethods);
		$method = $firstGroup === false ? false : reset($firstGroup);
		if (!$method instanceof IIdentifyMethod) {
			return $targets;
		}

		foreach ($this->fileMapper->getChildrenFiles($file->getId()) as $child) {
			$this->identifyMethodService->clearCache();
			$childRequest = $this->signRequestService->getSignRequestByIdentifyMethod($method, $child->getId());
			if ($childRequest->getId() !== null) {
				$targets[] = [$child, $childRequest];
			}
		}
		return $targets;
	}

	private function assertCanRemove(FileEntity $file, SignRequestEntity $signRequest): void {
		if (!(FileStatus::tryFrom($file->getStatus())?->allowsSignerRemoval() ?? false)
			|| $signRequest->hasCompletedSigning()
		) {
			$this->rejectRemoval();
		}
	}

	private function rejectRemoval(): never {
		// TRANSLATORS Error shown when removing a participant would discard a completed signature.
		throw new LibresignException($this->l10n->t('A completed signer cannot be removed.'), Http::STATUS_UNPROCESSABLE_ENTITY);
	}

	private function revertToDraftWhenEmpty(FileEntity $file): void {
		if ($this->signRequestMapper->getByFileId($file->getId()) !== []) {
			return;
		}

		$this->fileStatusService->markPolicySnapshotFrozen($file, FileStatus::DRAFT->value);
		$file->setStatus(FileStatus::DRAFT->value);
		$this->fileStatusService->update($file);
	}

	/**
	 * @param array<string, array<IIdentifyMethod>> $identifyMethods
	 */
	private function notifyCancellation(FileEntity $file, SignRequestEntity $signRequest, array $identifyMethods): void {
		foreach ($identifyMethods as $group) {
			foreach ($group as $identifyMethod) {
				try {
					$this->eventDispatcher->dispatchTyped(new SignRequestCanceledEvent($signRequest, $file, $identifyMethod));
				} catch (\Exception $e) {
					$this->logger->error('Unable to dispatch signer cancellation notification', [
						'exception' => $e,
						'signRequestId' => $signRequest->getId(),
					]);
				}
			}
		}
	}
}
