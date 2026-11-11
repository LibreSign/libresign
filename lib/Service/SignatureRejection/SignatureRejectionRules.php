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
use OCA\Libresign\Enum\SignatureRejectionCommentMode;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Exception\LibresignException;
use OCP\IL10N;

class SignatureRejectionRules {
	public const MAX_COMMENT_LENGTH = 4096;

	public function __construct(
		private SignatureRejectionPolicyService $policies,
		private IL10N $l10n,
	) {
	}

	public function validate(
		FileEntity $file,
		SignRequestEntity $signer,
		?string $comment,
		bool $privateComment,
	): RejectionInput {
		$policy = $this->policies->getConfig($file);
		if (!$policy->isEnabled()) {
			// TRANSLATORS Error shown when a signer tries to reject a document whose policy does not allow rejections.
			throw new LibresignException($this->l10n->t('Signature rejection is not enabled for this document.'));
		}

		$this->assertWorkflowIsOpen($file);
		$this->assertSignerCanReject($signer);
		$normalized = $this->normalizeComment($comment, $policy->getCommentMode());

		return new RejectionInput($normalized, $normalized !== null && $privateComment, $policy->cancelsWorkflow());
	}

	private function assertWorkflowIsOpen(FileEntity $libreSignFile): void {
		$openStatusList = [
			FileStatus::ABLE_TO_SIGN->value,
			FileStatus::PARTIAL_SIGNED->value,
		];
		if (in_array($libreSignFile->getStatus(), $openStatusList, true)) {
			return;
		}

		if ($libreSignFile->getStatus() === FileStatus::CANCELED->value) {
			// TRANSLATORS Error shown when an action is attempted on a document whose signing workflow was already closed by a rejection.
			throw new LibresignException($this->l10n->t('The signing workflow of this document is already closed.'));
		}

		// TRANSLATORS Error shown when a signer tries to reject a document that is not currently open for signing.
		throw new LibresignException($this->l10n->t('This document is not open for signature rejection.'));
	}

	private function assertSignerCanReject(SignRequestEntity $signRequest): void {
		if ($signRequest->getSigned() !== null) {
			// TRANSLATORS Error shown when the current user already signed this document.
			throw new LibresignException($this->l10n->t('File already signed by you'));
		}

		if ($signRequest->getStatusEnum() === SignRequestStatus::REJECTED) {
			// TRANSLATORS Error shown when a signer tries to reject the same signature request twice.
			throw new LibresignException($this->l10n->t('You already rejected this signature request.'));
		}
	}

	private function normalizeComment(?string $comment, SignatureRejectionCommentMode $mode): ?string {
		$trimmed = $comment === null ? '' : trim($comment);

		if ($mode === SignatureRejectionCommentMode::DISABLED) {
			if ($trimmed !== '') {
				// TRANSLATORS Error shown when a signer sends a rejection comment but the document policy does not accept comments.
				throw new LibresignException($this->l10n->t('Rejection comments are not allowed for this document.'));
			}
			return null;
		}

		if ($trimmed === '') {
			if ($mode === SignatureRejectionCommentMode::REQUIRED) {
				// TRANSLATORS Error shown when the document policy requires a justification and the signer did not provide one.
				throw new LibresignException($this->l10n->t('A comment is required to reject this signature request.'));
			}
			return null;
		}

		if (mb_strlen($trimmed) > self::MAX_COMMENT_LENGTH) {
			// TRANSLATORS Error shown when the rejection justification exceeds the maximum accepted length. %s is the maximum number of characters.
			throw new LibresignException($this->l10n->t('The rejection comment must have at most %s characters.', [(string)self::MAX_COMMENT_LENGTH]));
		}

		return $trimmed;
	}
}
