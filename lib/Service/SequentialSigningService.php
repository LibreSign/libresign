<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Enum\SignRequestStatus;
use Psr\Log\LoggerInterface;

class SequentialSigningService {
	private int $currentOrder = 1;
	private ?FileEntity $file = null;

	public function __construct(
		private SignRequestMapper $signRequestMapper,
		private IdentifyMethodService $identifyMethodService,
		private LoggerInterface $logger,
	) {
	}

	public function setFile(FileEntity $file): self {
		$this->file = $file;
		return $this;
	}

	public function isOrderedNumericFlow(): bool {
		return $this->getSignatureFlow() === SignatureFlow::ORDERED_NUMERIC;
	}

	/**
	 * Reset the internal order counter
	 */
	public function resetOrderCounter(): void {
		$this->currentOrder = 1;
	}

	/**
	 * Determine signing order based on flow configuration
	 * Manages internal counter automatically
	 *
	 * @param int|null $userProvidedOrder Order explicitly set by user
	 * @return int The order to use
	 */
	public function determineSigningOrder(?int $userProvidedOrder): int {
		if (!$this->isOrderedNumericFlow()) {
			return 1;
		}

		if ($userProvidedOrder !== null) {
			if ($userProvidedOrder >= $this->currentOrder) {
				$this->currentOrder = $userProvidedOrder + 1;
			}
			return $userProvidedOrder;
		}

		return $this->currentOrder++;
	}

	/**
	 * Release next order of signers after current order is completed
	 * Called when a signature is saved
	 *
	 * @param int $fileId
	 * @param int $completedOrder The order that was just completed
	 */
	public function releaseNextOrder(int $fileId, int $completedOrder): void {
		$this->notifyActivatedSigners($this->activateNextOrder($fileId, $completedOrder));
	}

	/**
	 * Same as releaseNextOrder(), but it only persists the activation and returns
	 * the signers it activated instead of notifying them.
	 *
	 * A caller running inside a transaction must collect the returned signers and
	 * call notifyActivatedSigners() only after the commit: a notification sent
	 * before the commit is already delivered when a rollback reverts the
	 * activation it announced.
	 *
	 * @return list<SignRequestEntity> The signers moved to ABLE_TO_SIGN.
	 */
	public function activateNextOrder(int $fileId, int $completedOrder): array {
		if (!$this->isOrderedNumericFlow()) {
			return [];
		}

		$allSignRequests = $this->signRequestMapper->getByFileId($fileId);

		if (!$this->isOrderFullyCompleted($allSignRequests, $completedOrder)) {
			return [];
		}

		$nextOrder = $this->findNextOrder($allSignRequests, $completedOrder);
		if ($nextOrder === null) {
			return [];
		}

		return $this->activateSignersForOrder($allSignRequests, $nextOrder);
	}

	/**
	 * Notify the signers returned by activateNextOrder().
	 *
	 * A delivery is best effort: one failing notification must not stop the
	 * remaining signers from being told that they can sign now, so every
	 * identify method is isolated from the others.
	 *
	 * Only recoverable failures (\Exception) are isolated. The concrete
	 * exception classes depend on the installed notification channels (mail,
	 * notifications, gateways), so the recoverable family is caught here per
	 * signer and identify method. A programming error (\Error) is not a
	 * delivery failure and stays visible instead of being logged as one.
	 *
	 * @param list<SignRequestEntity> $signers
	 */
	public function notifyActivatedSigners(array $signers): void {
		foreach ($signers as $signer) {
			$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signer->getId());
			foreach ($identifyMethods as $methodGroup) {
				foreach ($methodGroup as $identifyMethod) {
					$identifyMethod->willNotifyUser(true);
					try {
						$identifyMethod->notify();
					} catch (\Exception $e) {
						$this->logger->error('Error notifying an activated signer: ' . $e->getMessage(), [
							'exception' => $e,
							'signRequestId' => $signer->getId(),
							'identifyMethod' => $identifyMethod->getName(),
						]);
					}
				}
			}
		}
	}

	/**
	 * Reorder and activate signers after a SignRequest deletion
	 * This ensures no gaps in the signing sequence
	 *
	 * @param int $fileId The file ID
	 * @param int $deletedOrder The order that was deleted
	 */
	public function reorderAfterDeletion(int $fileId, int $deletedOrder): void {
		if (!$this->isOrderedNumericFlow()) {
			return;
		}

		$allSignRequests = $this->signRequestMapper->getByFileId($fileId);

		$hasSignersAtDeletedOrder = !empty(array_filter(
			$allSignRequests,
			fn ($sr) => $sr->getSigningOrder() === $deletedOrder
		));

		if (!$hasSignersAtDeletedOrder) {
			$previousOrder = $deletedOrder - 1;
			if ($previousOrder > 0 && $this->isOrderFullyCompleted($allSignRequests, $previousOrder)) {
				$nextOrder = $this->findNextOrder($allSignRequests, $deletedOrder);
				if ($nextOrder !== null) {
					$this->notifyActivatedSigners($this->activateSignersForOrder($allSignRequests, $nextOrder));
				}
			}
		}
	}

	/**
	 * A rejected signer will never sign, so like in hasPendingLowerOrderSigners()
	 * it must not hold the next order back when the workflow continues after a
	 * rejection.
	 */
	private function isOrderFullyCompleted(array $signRequests, int $order): bool {
		$pendingSigners = array_filter(
			$signRequests,
			fn ($sr) => $this->isSigningParticipant($sr)
				&& $sr->getSigningOrder() === $order
				&& $sr->getStatusEnum() !== SignRequestStatus::SIGNED
				&& $sr->getStatusEnum() !== SignRequestStatus::REJECTED
		);

		return empty($pendingSigners);
	}

	private function findNextOrder(array $signRequests, int $completedOrder): ?int {
		$allOrders = array_unique(array_map(
			fn ($sr) => $this->isSigningParticipant($sr) ? $sr->getSigningOrder() : null,
			$signRequests,
		));
		$allOrders = array_values(array_filter($allOrders, static fn (?int $order) => $order !== null && $order > 0));
		sort($allOrders);

		foreach ($allOrders as $order) {
			if ($order > $completedOrder) {
				return $order;
			}
		}

		return null;
	}

	/**
	 * @param list<SignRequestEntity> $signRequests
	 * @return list<SignRequestEntity> The signers moved to ABLE_TO_SIGN.
	 */
	private function activateSignersForOrder(array $signRequests, int $order): array {
		$activated = [];
		$signersToActivate = array_filter(
			$signRequests,
			fn ($sr) => $this->isSigningParticipant($sr)
				&& $sr->getSigningOrder() === $order
		);

		foreach ($signersToActivate as $signer) {
			if ($signer->getStatusEnum() === SignRequestStatus::DRAFT) {
				$signer->setStatusEnum(SignRequestStatus::ABLE_TO_SIGN);
				$this->signRequestMapper->update($signer);
				$activated[] = $signer;
			}
		}

		return $activated;
	}

	private function getSignatureFlow(): SignatureFlow {
		if ($this->file === null) {
			throw new \LogicException('File must be set before calling getSignatureFlow(). Call setFile() first.');
		}
		return $this->file->getSignatureFlowEnum();
	}

	/**
	 * Check if there are signers with lower signing order that haven't signed yet
	 */
	public function hasPendingLowerOrderSigners(int $fileId, int $currentOrder): bool {
		$signRequests = $this->signRequestMapper->getByFileId($fileId);

		foreach ($signRequests as $signRequest) {
			if (!$this->isSigningParticipant($signRequest)) {
				continue;
			}

			$order = $signRequest->getSigningOrder();
			$status = $signRequest->getStatusEnum();

			// If a signer with lower order hasn't signed yet, return true.
			// A rejected signer will never sign, so it must not block the remaining
			// signers when the workflow is configured to continue after a rejection.
			if ($order < $currentOrder
				&& $status !== SignRequestStatus::SIGNED
				&& $status !== SignRequestStatus::REJECTED
			) {
				return true;
			}
		}

		return false;
	}

	private function isSigningParticipant(\OCA\Libresign\Db\SignRequest $signRequest): bool {
		return $signRequest->getParticipantRoleEnum()->canSign();
	}

	/**
	 * Check if changing from currentStatus to desiredStatus is an upgrade (or same level)
	 * Status hierarchy: DRAFT (0) < ABLE_TO_SIGN (1) < SIGNED (2)
	 */
	public function isStatusUpgrade(
		SignRequestStatus $currentStatus,
		SignRequestStatus $desiredStatus,
	): bool {
		return $desiredStatus->value >= $currentStatus->value;
	}

	/**
	 * Validate if a signer can transition to ABLE_TO_SIGN status based on signing order
	 * In ordered numeric flow, prevents skipping ahead if lower-order signers haven't signed
	 *
	 * @param SignRequestStatus $desiredStatus The status being requested
	 * @param int $signingOrder The signer's order
	 * @param int $fileId The file ID
	 * @return SignRequestStatus The validated status (may return DRAFT if validation fails)
	 */
	public function validateStatusByOrder(
		SignRequestStatus $desiredStatus,
		int $signingOrder,
		int $fileId,
	): SignRequestStatus {
		// Only validate for ordered numeric flow
		if (!$this->isOrderedNumericFlow()) {
			return $desiredStatus;
		}

		// Only validate when trying to set ABLE_TO_SIGN and not the first signer
		if ($desiredStatus !== SignRequestStatus::ABLE_TO_SIGN || $signingOrder <= 1) {
			return $desiredStatus;
		}

		// Check if any lower order signers haven't signed yet
		if ($this->hasPendingLowerOrderSigners($fileId, $signingOrder)) {
			return SignRequestStatus::DRAFT;
		}

		return $desiredStatus;
	}
}
