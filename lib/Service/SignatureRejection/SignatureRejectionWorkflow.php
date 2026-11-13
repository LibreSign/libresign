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
use OCA\Libresign\Service\File\SigningWorkflowStatusResolver;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SequentialSigningService;
use OCP\AppFramework\Db\DoesNotExistException;

class SignatureRejectionWorkflow {
	public function __construct(
		private SignRequestMapper $signRequestMapper,
		private FileMapper $fileMapper,
		private IdentifyMethodService $identifyMethodService,
		private SequentialSigningService $sequentialSigningService,
		private SigningWorkflowStatusResolver $signingWorkflowStatusResolver,
		private FileStatusService $fileStatusService,
	) {
	}

	/** @param list<SignRequestEntity> $signers
	 * @return list<SignRequestEntity>
	 */
	public function continueWorkflow(FileEntity $file, array $signers): array {
		$activated = $this->releaseNextSigningOrders($file, $signers);
		$this->resolveStatusOfRejectedDocuments($file, $signers);
		return $activated;
	}

	/** @return list<SignRequestEntity> */
	public function collectSignRequestsToReject(FileEntity $libreSignFile, SignRequestEntity $signRequest): array {
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
		} catch (DoesNotExistException) {
		}

		// A document this signer already signed keeps its signature.
		return array_values(array_filter(
			$collected,
			static fn (SignRequestEntity $each): bool => $each->getSigned() === null,
		));
	}

	/**
	 * When the workflow continues, a rejection ends the signer's turn just like a
	 * signature does, so in a sequential flow the next order must be released on
	 * every document the signer rejected, or the signers after them would wait
	 * forever.
	 *
	 * @param list<SignRequestEntity> $rejectedSignRequests
	 * @return list<SignRequestEntity> The signers activated on every document.
	 */
	private function releaseNextSigningOrders(FileEntity $libreSignFile, array $rejectedSignRequests): array {
		$activatedSigners = [];

		foreach ($rejectedSignRequests as $rejected) {
			$document = $rejected->getFileId() === $libreSignFile->getId()
				? $libreSignFile
				: $this->fileMapper->getById($rejected->getFileId());
			foreach ($this->sequentialSigningService
				->setFile($document)
				->activateNextOrder(
					$rejected->getFileId(),
					$rejected->getSigningOrder()
				) as $activated) {
				$activatedSigners[] = $activated;
			}
		}

		return $activatedSigners;
	}

	/**
	 * A rejection ends the signer's turn just like a signature does, so it can be
	 * the last action a document was waiting for. Its status is then resolved by
	 * the same rule the signing flow uses, otherwise a document whose last signer
	 * rejected would stay partially signed forever.
	 *
	 * An envelope is not resolved here: its status follows the documents it
	 * contains, so it is read back once they are saved.
	 *
	 * @param list<SignRequestEntity> $rejectedSignRequests
	 */
	private function resolveStatusOfRejectedDocuments(FileEntity $libreSignFile, array $rejectedSignRequests): void {
		foreach ($rejectedSignRequests as $rejected) {
			$document = $rejected->getFileId() === $libreSignFile->getId()
				? $libreSignFile
				: $this->fileMapper->getById($rejected->getFileId());
			if ($document->isEnvelope()) {
				continue;
			}

			$status = $this->signingWorkflowStatusResolver->resolve(
				$document,
				$this->signRequestMapper->getByFileId($document->getId()),
			);
			if ($status === null || $status->value === $document->getStatus()) {
				continue;
			}

			$document->setStatusEnum($status);
			$this->fileStatusService->update($document);
		}

		if ($libreSignFile->isEnvelope()) {
			$libreSignFile->setStatus($this->fileMapper->getById($libreSignFile->getId())->getStatus());
		}
	}

	/**
	 * A rejection closes the whole envelope, not only the document that carried
	 * the rejected signature request.
	 */
	public function cancelWorkflow(FileEntity $libreSignFile): void {
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
}
