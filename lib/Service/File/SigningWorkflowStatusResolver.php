<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\IdDocs;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignatureRejectionBehavior;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionPolicyService;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Works out the status a document reaches from what its signers did. A
 * signature and a rejection both end a signer's turn, so both flows resolve
 * the status here and cannot disagree about when the workflow is over.
 */
class SigningWorkflowStatusResolver {
	public function __construct(
		private IdDocsMapper $idDocsMapper,
		private SignatureRejectionPolicyService $signatureRejectionPolicyService,
	) {
	}

	/**
	 * @param SignRequestEntity[] $signRequests every sign request of the document
	 * @return FileStatus|null null when the signers give no reason to change the status
	 */
	public function resolve(FileEntity $file, array $signRequests): ?FileStatus {
		$signers = $this->excludeIdDocUploaderPlaceholder($file, $signRequests);
		$signers = array_values(array_filter(
			$signers,
			static fn (SignRequestEntity $signer): bool => !$signer->isObserver(),
		));

		$total = count($signers);

		if ($total === 0) {
			return null;
		}

		$totalSigned = count(array_filter($signers, static fn (SignRequestEntity $signer): bool => $signer->getSigned() !== null));

		if ($totalSigned === $total) {
			return FileStatus::SIGNED;
		}

		if ($this->workflowCompletedDespiteRejections($file, $signers, $totalSigned)) {
			return FileStatus::SIGNED;
		}

		if ($totalSigned > 0) {
			return FileStatus::PARTIAL_SIGNED;
		}

		return null;
	}

	/**
	 * A rejection configured not to cancel the workflow leaves behind a signer
	 * who will never sign, so counting signatures alone would keep the file
	 * partially signed forever. The workflow is complete once nobody is expected
	 * to act any more and at least one signature was actually applied.
	 *
	 * A rejected signer keeps its own status: only the file-level status says
	 * that the configured workflow finished.
	 *
	 * @param SignRequestEntity[] $signers
	 */
	private function workflowCompletedDespiteRejections(FileEntity $file, array $signers, int $totalSigned): bool {
		if ($totalSigned === 0) {
			return false;
		}

		if ($this->signatureRejectionPolicyService->getBehavior($file) !== SignatureRejectionBehavior::CONTINUE) {
			return false;
		}

		$totalRejected = count(array_filter(
			$signers,
			static fn (SignRequestEntity $signer): bool => $signer->getStatusEnum() === SignRequestStatus::REJECTED,
		));

		return $totalSigned + $totalRejected === count($signers);
	}

	/**
	 * @param SignRequestEntity[] $signRequests
	 * @return SignRequestEntity[]
	 */
	private function excludeIdDocUploaderPlaceholder(FileEntity $file, array $signRequests): array {
		$placeholderId = $this->getIdDocUploaderSignRequestId($file);
		if ($placeholderId === null) {
			return $signRequests;
		}

		return array_values(array_filter(
			$signRequests,
			static fn (SignRequestEntity $signer): bool => $signer->getId() !== $placeholderId,
		));
	}

	private function getIdDocUploaderSignRequestId(FileEntity $file): ?int {
		try {
			$idDocs = $this->idDocsMapper->getByFileId($file->getId());
		} catch (DoesNotExistException) {
			return null;
		}

		if (!$idDocs instanceof IdDocs) {
			return null;
		}

		return $idDocs->getSignRequestId();
	}
}
