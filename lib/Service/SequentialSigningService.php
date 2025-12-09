<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\SignRequestStatus;
use OCP\IAppConfig;

class SequentialSigningService {
	private int $currentOrder = 1;

	public function __construct(
		private IAppConfig $appConfig,
		private SignRequestMapper $signRequestMapper,
		private IdentifyMethodService $identifyMethodService,
	) {
	}

	/**
	 * Get the current signature flow mode from global config
	 *
	 * @return SignatureFlow
	 */
	public function getSignatureFlow(): SignatureFlow {
		$value = $this->appConfig->getValueString(
			Application::APP_ID,
			'signature_flow',
			SignatureFlow::PARALLEL->value
		);

		return SignatureFlow::from($value);
	}

	/**
	 * Check if ordered numeric flow is enabled
	 */
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
			if ($userProvidedOrder > $this->currentOrder) {
				$this->currentOrder = $userProvidedOrder;
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
		if (!$this->isOrderedNumericFlow()) {
			return;
		}

		$allSignRequests = $this->signRequestMapper->getByFileId($fileId);

		if (!$this->isOrderFullyCompleted($allSignRequests, $completedOrder)) {
			return;
		}

		$nextOrder = $this->findNextOrder($allSignRequests, $completedOrder);
		if ($nextOrder === null) {
			return;
		}

		$this->activateSignersForOrder($allSignRequests, $nextOrder);
	}

	private function isOrderFullyCompleted(array $signRequests, int $order): bool {
		$pendingSigners = array_filter(
			$signRequests,
			fn ($sr) => $sr->getSigningOrder() === $order
				&& $sr->getStatusEnum() !== SignRequestStatus::SIGNED
		);

		return empty($pendingSigners);
	}

	private function findNextOrder(array $signRequests, int $completedOrder): ?int {
		$allOrders = array_unique(array_map(fn ($sr) => $sr->getSigningOrder(), $signRequests));
		sort($allOrders);

		foreach ($allOrders as $order) {
			if ($order > $completedOrder) {
				return $order;
			}
		}

		return null;
	}

	private function activateSignersForOrder(array $signRequests, int $order): void {
		$signersToActivate = array_filter(
			$signRequests,
			fn ($sr) => $sr->getSigningOrder() === $order
		);

		foreach ($signersToActivate as $signer) {
			if ($signer->getStatusEnum() === SignRequestStatus::DRAFT) {
				$signer->setStatusEnum(SignRequestStatus::ABLE_TO_SIGN);
				$this->signRequestMapper->update($signer);

				$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signer->getId());
				foreach ($identifyMethods as $methodGroup) {
					foreach ($methodGroup as $identifyMethod) {
						$identifyMethod->willNotifyUser(true);
						$identifyMethod->notify();
					}
				}
			}
		}
	}
}
