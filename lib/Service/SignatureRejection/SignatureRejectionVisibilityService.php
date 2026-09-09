<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureRejection;

use DateTimeInterface;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Enum\SignRequestStatus;

/**
 * Decides how much of a rejection is disclosed to whoever is reading the API.
 *
 * Everything is hidden by default: only the person who requested the signature
 * and the signer who rejected always see the whole record. Other readers see
 * what the document policy makes public.
 *
 * A comment the signer marked as private is never disclosed to them, whatever
 * the policy says: the rejection changes the workflow so its status may need to
 * be shared, but the words the signer wrote are their own.
 */
class SignatureRejectionVisibilityService {
	public function __construct(
		private SignatureRejectionPolicyService $rejectionPolicyService,
	) {
	}

	/**
	 * @param bool $privileged True for the requester of the signature and for the signer who rejected
	 * @return array{rejectedAt: string, comment?: string, commentPrivate?: bool}|null
	 */
	public function buildSignerRejection(
		SignRequestEntity $signRequest,
		?FileEntity $libreSignFile,
		bool $privileged,
	): ?array {
		$rejectedAt = $signRequest->getRejectedAt();
		if ($signRequest->getStatusEnum() !== SignRequestStatus::REJECTED || $rejectedAt === null) {
			return null;
		}

		$policy = $this->rejectionPolicyService->getPolicyValue($libreSignFile);
		if (!$privileged && !$policy['public_status']) {
			return null;
		}

		$rejection = [
			'rejectedAt' => $rejectedAt->format(DateTimeInterface::ATOM),
		];

		$comment = $signRequest->getRejectionComment();
		if ($comment === null || $comment === '') {
			return $rejection;
		}

		$commentIsPrivate = $signRequest->getRejectionCommentPrivate();
		if ($privileged) {
			$rejection['comment'] = $comment;
			$rejection['commentPrivate'] = $commentIsPrivate;
			return $rejection;
		}

		if ($commentIsPrivate || !$policy['show_comment_on_validation']) {
			return $rejection;
		}

		$rejection['comment'] = $comment;
		$rejection['commentPrivate'] = false;

		return $rejection;
	}
}
