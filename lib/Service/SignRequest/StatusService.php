<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignRequest;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\SequentialSigningService;

class StatusService {
	public function __construct(
		private SequentialSigningService $sequentialSigningService,
		private FileStatusService $fileStatusService,
		private StatusCacheService $statusCacheService,
	) {
	}

	public function shouldNotifySignRequest(SignRequestStatus $signRequestStatus, ?int $fileStatus): bool {
		return $this->fileStatusService->canNotifySigners($fileStatus)
			&& $this->canNotifySignRequest($signRequestStatus);
	}

	public function canNotifySignRequest(SignRequestStatus $status): bool {
		return $status === SignRequestStatus::ABLE_TO_SIGN;
	}

	public function cacheFileStatus(FileEntity $file): void {
		$this->statusCacheService->setStatus($file->getUuid(), $file->getStatus());
	}

	public function updateStatusIfAllowed(
		SignRequestEntity $signRequest,
		SignRequestStatus $currentStatus,
		SignRequestStatus $desiredStatus,
		bool $isNewSignRequest,
	): void {
		if ($isNewSignRequest) {
			$signRequest->setStatusEnum($desiredStatus);
			return;
		}

		if ($this->sequentialSigningService->isStatusUpgrade($currentStatus, $desiredStatus)) {
			$signRequest->setStatusEnum($desiredStatus);
			return;
		}

		if (
			$desiredStatus === SignRequestStatus::DRAFT
			&& $currentStatus === SignRequestStatus::ABLE_TO_SIGN
			&& $this->sequentialSigningService->isOrderedNumericFlow()
			&& $this->sequentialSigningService->hasPendingLowerOrderSigners(
				$signRequest->getFileId(),
				$signRequest->getSigningOrder()
			)
		) {
			$signRequest->setStatusEnum($desiredStatus);
		}
	}

	public function determineInitialStatus(
		int $signingOrder,
		int $fileId,
		?int $fileStatus = null,
		?int $signerStatus = null,
		?SignRequestStatus $currentStatus = null,
	): SignRequestStatus {
		if ($fileStatus === FileStatus::DRAFT->value) {
			return SignRequestStatus::DRAFT;
		}

		if ($fileStatus === FileStatus::ABLE_TO_SIGN->value) {
			return $this->determineStatusForAbleToSignFile($signingOrder);
		}

		if ($signerStatus !== null) {
			return $this->handleExplicitSignerStatus($signerStatus, $signingOrder, $fileId, $currentStatus);
		}

		return $this->getDefaultStatusByFlow($signingOrder);
	}

	private function determineStatusForAbleToSignFile(int $signingOrder): SignRequestStatus {
		if ($this->sequentialSigningService->isOrderedNumericFlow()) {
			return $signingOrder === 1 ? SignRequestStatus::ABLE_TO_SIGN : SignRequestStatus::DRAFT;
		}
		return SignRequestStatus::ABLE_TO_SIGN;
	}

	private function handleExplicitSignerStatus(
		int $signerStatus,
		int $signingOrder,
		int $fileId,
		?SignRequestStatus $currentStatus,
	): SignRequestStatus {
		$desiredStatus = SignRequestStatus::from($signerStatus);

		if ($currentStatus !== null && !$this->sequentialSigningService->isStatusUpgrade($currentStatus, $desiredStatus)) {
			return $currentStatus;
		}

		return $this->sequentialSigningService->validateStatusByOrder($desiredStatus, $signingOrder, $fileId);
	}

	private function getDefaultStatusByFlow(int $signingOrder): SignRequestStatus {
		if (!$this->sequentialSigningService->isOrderedNumericFlow()) {
			return SignRequestStatus::ABLE_TO_SIGN;
		}

		return $signingOrder === 1 ? SignRequestStatus::ABLE_TO_SIGN : SignRequestStatus::DRAFT;
	}
}
