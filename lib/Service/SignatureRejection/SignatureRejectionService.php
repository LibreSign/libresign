<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureRejection;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
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
use OCP\IDBConnection;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * Records a signer's refusal to sign a document.
 *
 * The rules that govern a rejection (whether it is allowed at all, whether a
 * comment is required, and whether the remaining workflow keeps running) come
 * from the signature rejection policy frozen on the document.
 */
class SignatureRejectionService {
	public const MAX_COMMENT_LENGTH = 4096;

	public function __construct(
		private SignRequestMapper $signRequestMapper,
		private FileMapper $fileMapper,
		private SignatureRejectionPolicyService $rejectionPolicyService,
		private FileStatusService $fileStatusService,
		private IdentifyMethodService $identifyMethodService,
		private IEventDispatcher $eventDispatcher,
		private IDBConnection $db,
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
		// A comment can carry personal or legal information, so keeping it private is
		// always the signer's call and no policy can take that choice away.
		$commentIsPrivate = $normalizedComment !== null && $privateComment;

		$workflowCanceled = $policy['cancel_workflow'];
		$this->persistRejection($libreSignFile, $signRequest, $normalizedComment, $commentIsPrivate, $workflowCanceled);
		$this->dispatchRejectedEvent($signRequest, $libreSignFile, $workflowCanceled);

		return $signRequest;
	}

	public function isRejected(SignRequestEntity $signRequest): bool {
		return $signRequest->getStatusEnum() === SignRequestStatus::REJECTED;
	}

	public function isWorkflowCanceled(FileEntity $libreSignFile): bool {
		return $libreSignFile->getStatus() === FileStatus::CANCELED->value;
	}

	/**
	 * Marking the signer as rejected and closing the workflow describe a single
	 * decision, so they are written together: a failure must never leave a signer
	 * who already rejected on a document that stays open for everybody else.
	 */
	private function persistRejection(
		FileEntity $libreSignFile,
		SignRequestEntity $signRequest,
		?string $comment,
		bool $commentIsPrivate,
		bool $workflowCanceled,
	): void {
		$signRequests = $this->collectSignRequestsToReject($libreSignFile, $signRequest);
		$previousSignerStatuses = array_map(
			static fn (SignRequestEntity $each): int => $each->getStatus(),
			$signRequests,
		);
		$previousFileStatus = $libreSignFile->getStatus();
		$rejectedAt = $this->timeFactory->getDateTime();

		$this->db->beginTransaction();
		try {
			foreach ($signRequests as $each) {
				$each->setStatusEnum(SignRequestStatus::REJECTED);
				$each->setRejectedAt($rejectedAt);
				$each->setRejectionComment($comment);
				$each->setRejectionCommentPrivate($commentIsPrivate);
				$this->signRequestMapper->update($each);
			}

			if ($workflowCanceled) {
				$this->cancelWorkflow($libreSignFile);
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			$this->restoreState($libreSignFile, $signRequests, $previousSignerStatuses, $previousFileStatus);
			$this->logger->error('Failed to register the signature rejection: ' . $e->getMessage(), [
				'exception' => $e,
				'signRequestId' => $signRequest->getId(),
			]);

			// TRANSLATORS Error shown when the rejection could not be saved and nothing was changed.
			throw new LibresignException($this->l10n->t('It was not possible to register the rejection. Nothing was changed.'));
		}
	}

	/**
	 * A signer of an envelope has one signature request per document it contains,
	 * plus one on the envelope itself. Signing closes all of them, so a rejection
	 * has to close all of them too: recording it on a single document would leave
	 * the refusal invisible to the person who requested the signature, whose view
	 * of the envelope is built from the envelope-level signature requests.
	 *
	 * @return list<SignRequestEntity>
	 */
	private function collectSignRequestsToReject(FileEntity $libreSignFile, SignRequestEntity $signRequest): array {
		$envelopeId = $libreSignFile->isEnvelope()
			? $libreSignFile->getId()
			: $libreSignFile->getParentFileId();
		if ($envelopeId === null) {
			return [$signRequest];
		}

		$collected = [$signRequest->getId() => $signRequest];

		foreach ($this->signRequestMapper->getByEnvelopeChildrenAndIdentifyMethod($envelopeId, $signRequest->getId()) as $childSignRequest) {
			$collected[$childSignRequest->getId()] ??= $childSignRequest;
		}

		try {
			$envelopeSignRequest = $this->signRequestMapper->getByIdentifyMethodAndFileId(
				$this->identifyMethodService->getIdentifiedMethod($signRequest->getId()),
				$envelopeId,
			);
			$collected[$envelopeSignRequest->getId()] ??= $envelopeSignRequest;
		} catch (\Throwable) {
			// The envelope itself carries no signature request for this signer.
		}

		// A document this signer already signed keeps its signature.
		return array_values(array_filter(
			$collected,
			static fn (SignRequestEntity $each): bool => $each->getSigned() === null,
		));
	}

	/**
	 * A rejection closes the whole envelope, not only the document that carried
	 * the rejected signature request.
	 */
	private function cancelWorkflow(FileEntity $libreSignFile): void {
		$libreSignFile->setStatusEnum(FileStatus::CANCELED);
		$this->fileStatusService->update($libreSignFile);

		$envelopeId = $libreSignFile->isEnvelope()
			? $libreSignFile->getId()
			: $libreSignFile->getParentFileId();
		if ($envelopeId === null) {
			return;
		}

		$this->fileStatusService->propagateStatusToChildren($envelopeId, FileStatus::CANCELED->value);

		if (!$libreSignFile->isEnvelope()) {
			$envelope = $this->fileMapper->getById($envelopeId);
			$envelope->setStatusEnum(FileStatus::CANCELED);
			$this->fileStatusService->update($envelope);
		}
	}

	/**
	 * @param list<SignRequestEntity> $signRequests
	 * @param list<int> $previousSignerStatuses
	 */
	private function restoreState(
		FileEntity $libreSignFile,
		array $signRequests,
		array $previousSignerStatuses,
		int $previousFileStatus,
	): void {
		foreach ($signRequests as $index => $signRequest) {
			$signRequest->setStatus($previousSignerStatuses[$index]);
			$signRequest->setRejectedAt(null);
			$signRequest->setRejectionComment(null);
			$signRequest->setRejectionCommentPrivate(false);
		}
		$libreSignFile->setStatus($previousFileStatus);
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
		// The rejection is already committed at this point: a failing listener must
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
