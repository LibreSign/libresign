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
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionPolicyService;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Shared document status resolution for signing and rejection in stable35.
 * A rejection only completes a continuing workflow if at least one signature exists.
 */
class SigningWorkflowStatusResolver {
	public function __construct(
		private IdDocsMapper $idDocsMapper,
		private SignatureRejectionPolicyService $rejectionPolicyService,
	) {
	}

	/**
	 * @param SignRequestEntity[] $signRequests
	 */
	public function resolve(FileEntity $file, array $signRequests): ?FileStatus {
		$signers = $this->excludeIdDocUploaderPlaceholder($file, $signRequests);
		$total = count($signers);
		if ($total === 0) {
			return null;
		}

		$totalSigned = count(array_filter($signers, static fn (SignRequestEntity $signer): bool => $signer->getSigned() !== null));
		if ($totalSigned === $total) {
			return FileStatus::SIGNED;
		}

		if ($totalSigned > 0) {
			$policy = $this->rejectionPolicyService->getPolicyValue($file);
			if ($policy['enabled'] && !$policy['cancel_workflow']) {
				$totalRejected = count(array_filter(
					$signers,
					static fn (SignRequestEntity $signer): bool => $signer->getStatusEnum() === SignRequestStatus::REJECTED,
				));
				if ($totalSigned + $totalRejected === $total) {
					return FileStatus::SIGNED;
				}
			}
			return FileStatus::PARTIAL_SIGNED;
		}

		return null;
	}

	/**
	 * @param SignRequestEntity[] $signRequests
	 * @return SignRequestEntity[]
	 */
	private function excludeIdDocUploaderPlaceholder(FileEntity $file, array $signRequests): array {
		$fileId = $file->getId();
		if ($fileId === null) {
			return $signRequests;
		}
		try {
			$idDocs = $this->idDocsMapper->getByFileId($fileId);
		} catch (DoesNotExistException) {
			return $signRequests;
		}
		if (!$idDocs instanceof IdDocs) {
			return $signRequests;
		}
		$placeholderId = $idDocs->getSignRequestId();
		return array_values(array_filter(
			$signRequests,
			static fn (SignRequestEntity $signer): bool => $signer->getId() !== $placeholderId,
		));
	}
}
