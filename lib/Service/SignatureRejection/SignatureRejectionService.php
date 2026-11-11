<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureRejection;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Exception\LibresignException;

class SignatureRejectionService {
	public const MAX_COMMENT_LENGTH = SignatureRejectionRules::MAX_COMMENT_LENGTH;

	public function __construct(
		private SignatureRejectionRules $rules,
		private SignatureRejectionPersistence $persistence,
		private SignatureRejectionNotifier $notifier,
	) {
	}

	/** @throws LibresignException when the policy or workflow state forbids rejection */
	public function reject(
		FileEntity $libreSignFile,
		SignRequestEntity $signRequest,
		?string $comment = null,
		bool $privateComment = false,
	): SignRequestEntity {
		$input = $this->rules->validate($libreSignFile, $signRequest, $comment, $privateComment);
		$committed = $this->persistence->commit($libreSignFile, $signRequest, $input);
		$this->notifier->afterCommit($libreSignFile, $signRequest, $committed);

		return $signRequest;
	}

	public function isRejected(SignRequestEntity $signRequest): bool {
		return $signRequest->getStatusEnum() === SignRequestStatus::REJECTED;
	}

	public function isWorkflowCanceled(FileEntity $libreSignFile): bool {
		return $libreSignFile->getStatus() === FileStatus::CANCELED->value;
	}
}
