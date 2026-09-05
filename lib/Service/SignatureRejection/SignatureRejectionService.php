<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureRejection;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignatureRejectionCommentMode;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Events\SignatureRejectedEvent;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * Records a signer's refusal to sign a document.
 *
 * The rules that govern a rejection (whether it is allowed at all, whether a
 * comment is required, and whether the remaining workflow keeps running) come
 * from the signature rejection policy resolved for the document.
 */
class SignatureRejectionService {
	public const MAX_COMMENT_LENGTH = 4096;

	public function __construct(
		private SignRequestMapper $signRequestMapper,
		private SignatureRejectionPolicyService $rejectionPolicyService,
		private FileStatusService $fileStatusService,
		private IdentifyMethodService $identifyMethodService,
		private IEventDispatcher $eventDispatcher,
		private ITimeFactory $timeFactory,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @throws LibresignException when the policy or the current workflow state forbids the rejection
	 */
	public function reject(
		FileEntity $libreSignFile,
		SignRequestEntity $signRequest,
		?string $comment = null,
		bool $privateComment = false,
	): SignRequestEntity {
		$policy = $this->rejectionPolicyService->getPolicyValue($libreSignFile);

		if (!$policy['enabled']) {
			// TRANSLATORS Error shown when a signer tries to reject a document whose policy does not allow rejections.
			throw new LibresignException($this->l10n->t('Signature rejection is not enabled for this document.'));
		}

		$this->assertWorkflowIsOpen($libreSignFile);
		$this->assertSignerCanReject($signRequest);

		$normalizedComment = $this->normalizeComment($comment, $policy['comment_mode']);
		$commentIsPrivate = $normalizedComment !== null && $privateComment;
		if ($commentIsPrivate && !$policy['allow_private_comment']) {
			// TRANSLATORS Error shown when a signer asks to keep the rejection comment private but the document policy does not allow it.
			throw new LibresignException($this->l10n->t('Private rejection comments are not allowed for this document.'));
		}

		$signRequest->setStatusEnum(SignRequestStatus::REJECTED);
		$signRequest->setRejectedAt($this->timeFactory->getDateTime());
		$signRequest->setRejectionComment($normalizedComment);
		$signRequest->setRejectionCommentPrivate($commentIsPrivate);
		$this->signRequestMapper->update($signRequest);

		$workflowCanceled = $policy['cancel_workflow'];
		if ($workflowCanceled) {
			$libreSignFile->setStatusEnum(FileStatus::CANCELED);
			$this->fileStatusService->update($libreSignFile);
		}

		$this->dispatchRejectedEvent($signRequest, $libreSignFile, $workflowCanceled);

		return $signRequest;
	}

	public function isRejected(SignRequestEntity $signRequest): bool {
		return $signRequest->getStatusEnum() === SignRequestStatus::REJECTED;
	}

	public function isWorkflowCanceled(FileEntity $libreSignFile): bool {
		return $libreSignFile->getStatus() === FileStatus::CANCELED->value;
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

		if ($this->isRejected($signRequest)) {
			// TRANSLATORS Error shown when a signer tries to reject the same signature request twice.
			throw new LibresignException($this->l10n->t('You already rejected this signature request.'));
		}
	}

	private function normalizeComment(?string $comment, string $commentMode): ?string {
		$mode = SignatureRejectionCommentMode::from($commentMode);
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

	private function dispatchRejectedEvent(
		SignRequestEntity $signRequest,
		FileEntity $libreSignFile,
		bool $workflowCanceled,
	): void {
		// The rejection is already persisted at this point: a failing listener must
		// not turn a recorded rejection into an error for the signer.
		try {
			$groupedIdentifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
			foreach ($groupedIdentifyMethods as $identifyMethods) {
				foreach ($identifyMethods as $identifyMethod) {
					$this->eventDispatcher->dispatchTyped(new SignatureRejectedEvent(
						$signRequest,
						$libreSignFile,
						$identifyMethod,
						$workflowCanceled,
					));
				}
			}
		} catch (\Throwable $e) {
			$this->logger->error('Error dispatching SignatureRejectedEvent: ' . $e->getMessage(), ['exception' => $e]);
		}
	}
}
