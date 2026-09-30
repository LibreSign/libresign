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
use OCA\Libresign\Enum\SignerDisplayStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCP\IL10N;

/**
 * Decides how much of a rejection is disclosed to whoever is reading the API.
 *
 * Everything is hidden by default: only the person who requested the signature
 * and the signer who rejected always see the whole record. Other readers see
 * what the document policy discloses to their audience (see RejectionViewer).
 *
 * A comment the signer marked as private is never disclosed to them, whatever
 * the policy says: the rejection changes the workflow so its status may need to
 * be shared, but the words the signer wrote are their own.
 */
class SignatureRejectionVisibilityService {
	public function __construct(
		private SignatureRejectionPolicyService $rejectionPolicyService,
		private IL10N $l10n,
	) {
	}

	/**
	 * Whether the file holds a rejection the viewer may not know about.
	 *
	 * A rejection is visible to the requester of the file and to the signer
	 * who rejected; everybody else only sees it when the policy discloses it
	 * to their audience. When one is hidden, redacting that signer alone would
	 * still point at them by comparison with the others, so the callers
	 * present every unsigned signer of the file the same way.
	 *
	 * @param SignRequestEntity[] $signers every sign request of the file
	 */
	public function hasHiddenRejection(?FileEntity $libreSignFile, array $signers, RejectionViewer $viewer): bool {
		$config = null;
		foreach ($signers as $signer) {
			if ($signer->getStatusEnum() !== SignRequestStatus::REJECTED) {
				continue;
			}
			if ($viewer->isPrivilegedFor($signer)) {
				continue;
			}
			$config ??= $this->rejectionPolicyService->getConfig($libreSignFile);
			if (!$config->isRejectionVisibleTo($viewer->getAudience())) {
				return true;
			}
		}
		return false;
	}

	/**
	 * How one signer is presented to the viewer.
	 *
	 * While a rejection is hidden, every unsigned signer is redacted, the
	 * viewer's own pending entry included: if they kept their real status
	 * they could tell who rejected by comparison. The only entry the viewer
	 * keeps is their own rejection, which they already know about.
	 *
	 * @param bool $hiddenRejectionInFile the result of hasHiddenRejection() for the file
	 */
	public function presentSigner(
		SignRequestEntity $signer,
		?FileEntity $libreSignFile,
		RejectionViewer $viewer,
		bool $hiddenRejectionInFile,
	): SignerPresentation {
		$status = $signer->getStatusEnum();
		// Only a participant who could have rejected is redacted: a signed
		// signer and an observer say nothing about who rejected, and the
		// viewer keeps their own rejection.
		$couldHaveRejected = $status !== SignRequestStatus::SIGNED && $status !== SignRequestStatus::OBSERVING;
		$ownRejection = $status === SignRequestStatus::REJECTED && $viewer->isPrivilegedFor($signer);
		if ($hiddenRejectionInFile && $couldHaveRejected && !$ownRejection) {
			return new SignerPresentation(
				SignerDisplayStatus::NOT_SIGNED,
				null,
				SignerDisplayStatus::NOT_SIGNED->getLabel($this->l10n),
				null,
			);
		}

		return new SignerPresentation(
			SignerDisplayStatus::fromSignRequestStatus($status),
			$status->value,
			$status->getLabel($this->l10n),
			$this->buildSignerRejection($signer, $libreSignFile, $viewer),
		);
	}

	/**
	 * @return array{rejectedAt: string, comment?: string, commentPrivate?: bool}|null
	 */
	public function buildSignerRejection(
		SignRequestEntity $signRequest,
		?FileEntity $libreSignFile,
		RejectionViewer $viewer,
	): ?array {
		$rejectedAt = $signRequest->getRejectedAt();
		if ($signRequest->getStatusEnum() !== SignRequestStatus::REJECTED || $rejectedAt === null) {
			return null;
		}

		$privileged = $viewer->isPrivilegedFor($signRequest);
		$config = $this->rejectionPolicyService->getConfig($libreSignFile);
		if (!$privileged && !$config->isRejectionVisibleTo($viewer->getAudience())) {
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

		if ($commentIsPrivate || !$config->isCommentVisibleTo($viewer->getAudience())) {
			return $rejection;
		}

		$rejection['comment'] = $comment;
		$rejection['commentPrivate'] = false;

		return $rejection;
	}
}
