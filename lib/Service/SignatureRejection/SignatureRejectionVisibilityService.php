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
use OCA\Libresign\Enum\SignatureRejectionVisibility;
use OCA\Libresign\Enum\SignerDisplayStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCP\IL10N;

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
 *
 * Only the public audience is told apart here: this service does not yet know
 * which reader it is answering, so a rejection kept among the participants is
 * still treated as private for every reader but the privileged ones. The reader
 * context arrives with the visibility work of #8388.
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
	 * who rejected; everybody else only sees it when the policy makes the
	 * status public. When one is hidden, redacting that signer alone would
	 * still point at them by comparison with the others, so the callers
	 * present every unsigned signer of the file the same way.
	 *
	 * @param SignRequestEntity[] $signers every sign request of the file
	 * @param list<int> $privilegedSignRequestIds sign requests the viewer is privileged for (the requester is privileged for all of them)
	 */
	public function hasHiddenRejection(?FileEntity $libreSignFile, array $signers, array $privilegedSignRequestIds): bool {
		$config = null;
		foreach ($signers as $signer) {
			if ($signer->getStatusEnum() !== SignRequestStatus::REJECTED) {
				continue;
			}
			if (in_array($signer->getId(), $privilegedSignRequestIds, true)) {
				continue;
			}
			$config ??= $this->rejectionPolicyService->getConfig($libreSignFile);
			if (!$config->isRejectionVisibleTo(SignatureRejectionVisibility::PUBLIC)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * How one signer is presented to the viewer.
	 *
	 * @param bool $privileged whether the viewer is the requester of the file or this very signer
	 * @param bool $hiddenRejectionInFile the result of hasHiddenRejection() for the file
	 */
	public function presentSigner(
		SignRequestEntity $signer,
		?FileEntity $libreSignFile,
		bool $privileged,
		bool $hiddenRejectionInFile,
	): SignerPresentation {
		$status = $signer->getStatusEnum();
		// Only a participant who could have rejected is redacted: a signed
		// signer and an observer say nothing about who rejected.
		$couldHaveRejected = $status !== SignRequestStatus::SIGNED && $status !== SignRequestStatus::OBSERVING;
		if ($hiddenRejectionInFile && $couldHaveRejected && !$privileged) {
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
			$this->buildSignerRejection($signer, $libreSignFile, $privileged),
		);
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

		$config = $this->rejectionPolicyService->getConfig($libreSignFile);
		if (!$privileged && !$config->isRejectionVisibleTo(SignatureRejectionVisibility::PUBLIC)) {
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

		if ($commentIsPrivate || !$config->isCommentVisibleTo(SignatureRejectionVisibility::PUBLIC)) {
			return $rejection;
		}

		$rejection['comment'] = $comment;
		$rejection['commentPrivate'] = false;

		return $rejection;
	}
}
