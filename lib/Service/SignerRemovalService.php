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
use OCA\Libresign\Service\SignerRemoval\RemovalChanges;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\TTransactional;
use OCP\AppFramework\Http;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * Coordinates an atomic signer removal and publishes notifications after commit.
 */
class SignerRemovalService {
	use TTransactional;

	public function __construct(
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private IdentifyMethodService $identifyMethodService,
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
		$changes = new RemovalChanges();
		try {
			$this->atomic(
				fn () => $this->persistRemoval($fileId, $signRequestId, $changes),
				$this->db,
			);
		} finally {
			// The database transaction does not roll back application caches.
			$this->invalidateCaches($changes);
		}

		$this->sequentialSigningService->notifyActivatedSigners($changes->activatedSigners);
		if ($notify && $changes->file !== null && $changes->signRequest !== null
			&& $changes->signRequest->getStatus() === SignRequestStatus::ABLE_TO_SIGN->value
		) {
			$this->notifyCancellation($changes->file, $changes->signRequest, $changes->identifyMethods);
		}
	}

	private function persistRemoval(int $fileId, int $signRequestId, RemovalChanges $changes): void {
		$targets = $this->getRemovalTargets($fileId, $signRequestId);
		[$changes->file, $changes->signRequest] = $targets[0];
		$changes->identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequestId);

		// Validate the full envelope before deleting anything.
		foreach ($targets as [$file, $signRequest, $siblings]) {
			$this->assertCanRemove($file, $signRequest, $siblings);
			$changes->track($file, $signRequest);
			if ($file->hasParent()) {
				$parent = $this->fileMapper->getById($file->getParentFileId());
				$changes->files[$parent->getId()] = $parent;
			}
		}

		foreach ($targets as [$file, $signRequest]) {
			if (!$this->signRequestMapper->deleteIfUnsigned($signRequest)) {
				$this->rejectRemoval();
			}
			$this->identifyMethodService->deleteBySignRequestId($signRequest->getId());
			foreach ($this->fileElementMapper->getByFileIdAndSignRequestId($file->getId(), $signRequest->getId()) as $element) {
				$this->fileElementMapper->delete($element);
			}
			foreach ($this->sequentialSigningService->activateAfterDeletion($file, $signRequest->getSigningOrder()) as $activated) {
				$changes->activated($activated);
			}
			$this->revertToDraftWhenEmpty($file);
		}
	}

	/**
	 * Lock the entire set of signing requests for each affected file, not only
	 * the removed signer. A concurrent signature must not invalidate preflight.
	 *
	 * @return list<array{FileEntity, SignRequestEntity, list<SignRequestEntity>}>
	 */
	private function getRemovalTargets(int $fileId, int $signRequestId): array {
		$rootSigners = $this->signRequestMapper->getByFileIdForUpdate($fileId);
		$file = $this->fileMapper->getByIdForUpdate($fileId);
		$targets = [[$file, $this->requireSigner($rootSigners, $signRequestId), $rootSigners]];
		if (!$file->isEnvelope()) {
			return $targets;
		}

		// The same database query is used by the envelope rejection workflow.
		// De-duplicate factor matches before acquiring locks for child files.
		$childIds = [];
		foreach ($this->signRequestMapper->getByEnvelopeChildrenAndIdentifyMethod($fileId, $signRequestId) as $childSigner) {
			$childIds[$childSigner->getFileId()][$childSigner->getId()] = true;
		}
		ksort($childIds);

		foreach ($childIds as $childFileId => $requestIds) {
			$signers = $this->signRequestMapper->getByFileIdForUpdate($childFileId);
			$child = $this->fileMapper->getByIdForUpdate($childFileId);
			if ($child->getParentFileId() !== $fileId) {
				throw new DoesNotExistException('Envelope child is no longer associated with its parent');
			}
			foreach (array_keys($requestIds) as $requestId) {
				$targets[] = [$child, $this->requireSigner($signers, $requestId), $signers];
			}
		}

		return $targets;
	}

	/**
	 * @param list<SignRequestEntity> $signers
	 */
	private function requireSigner(array $signers, int $signRequestId): SignRequestEntity {
		foreach ($signers as $signer) {
			if ($signer->getId() === $signRequestId) {
				return $signer;
			}
		}
		throw new DoesNotExistException('Sign request is not associated with the file');
	}

	/**
	 * @param list<SignRequestEntity> $siblings
	 */
	private function assertCanRemove(FileEntity $file, SignRequestEntity $signRequest, array $siblings): void {
		if (!(FileStatus::tryFrom($file->getStatus())?->allowsSignerRemoval() ?? false)
			|| $signRequest->hasCompletedSigning()
		) {
			$this->rejectRemoval();
		}

		// The file status may not yet reflect another signer's completed update.
		foreach ($siblings as $sibling) {
			if ($sibling->hasCompletedSigning()) {
				$this->rejectRemoval();
			}
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
	 * Cache failure must not turn an already committed removal into an API error,
	 * nor hide the original error if persistence rolled back.
	 */
	private function invalidateCaches(RemovalChanges $changes): void {
		foreach ($changes->signRequests as $signRequest) {
			try {
				$this->signRequestMapper->invalidateCachedEntity($signRequest);
			} catch (\Exception $e) {
				$this->logger->warning('Unable to invalidate signer cache after removal', [
					'exception' => $e,
					'signRequestId' => $signRequest->getId(),
				]);
			}
		}
		foreach ($changes->files as $file) {
			try {
				$this->fileMapper->invalidateCachedEntity($file);
			} catch (\Exception $e) {
				$this->logger->warning('Unable to invalidate file cache after signer removal', [
					'exception' => $e,
					'fileId' => $file->getId(),
				]);
			}
			try {
				// Invalidate instead of publishing a potentially stale worker status.
				$this->fileStatusService->invalidateCachedStatus($file);
			} catch (\Exception $e) {
				$this->logger->warning('Unable to invalidate file status cache after signer removal', [
					'exception' => $e,
					'fileId' => $file->getId(),
				]);
			}
		}
		$this->identifyMethodService->clearCache();
	}

	/** @param array<string, array<IIdentifyMethod>> $identifyMethods */
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
