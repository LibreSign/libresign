<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocs;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\ApprovalGroups\ApprovalGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\ApprovalGroups\ApprovalGroupsPolicyValue;
use OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicy;
use OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicyValue;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;

class IdDocsPolicyService {
	public function __construct(
		private PolicyService $policyService,
		private IGroupManager $groupManager,
		private IL10N $l10n,
		private IdDocsMapper $idDocsMapper,
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
	) {
	}

	public function canApproverSignIdDoc(IUser $user, int $fileId, int $status): bool {
		try {
			$idDocs = $this->idDocsMapper->getByFileId($fileId);
		} catch (DoesNotExistException) {
			return false;
		}

		if (!$this->isIdentificationDocumentsEnabled($user, $this->getRelatedSignRequest($idDocs))) {
			return false;
		}
		if (!$this->userCanApproveValidationDocuments($user, false)) {
			return false;
		}
		$readyStatuses = [FileStatus::ABLE_TO_SIGN->value, FileStatus::PARTIAL_SIGNED->value];
		if (!in_array($status, $readyStatuses, true)) {
			return false;
		}

		return true;
	}

	public function isIdentificationDocumentsEnabled(?IUser $user = null, ?SignRequest $signRequest = null): bool {
		$snapshotValue = $this->getSnapshotValue($signRequest);
		if ($snapshotValue !== null) {
			return IdentificationDocumentsPolicyValue::isEnabled($snapshotValue, false);
		}

		$resolved = $user
			? $this->policyService->resolveForUser(IdentificationDocumentsPolicy::KEY, $user)
			: $this->policyService->resolve(IdentificationDocumentsPolicy::KEY);
		$value = $resolved->getEffectiveValue();
		return IdentificationDocumentsPolicyValue::isEnabled($value, false);
	}

	/**
	 * Get approver group IDs for identification documents flow.
	 *
	 * @return list<string>
	 */
	public function getApproverGroups(?IUser $user = null, ?SignRequest $signRequest = null): array {
		$snapshotValue = $this->getSnapshotValue($signRequest);
		if ($snapshotValue !== null) {
			return IdentificationDocumentsPolicyValue::getApprovers($snapshotValue);
		}

		$resolved = $user
			? $this->policyService->resolveForUser(IdentificationDocumentsPolicy::KEY, $user)
			: $this->policyService->resolve(IdentificationDocumentsPolicy::KEY);
		$value = $resolved->getEffectiveValue();
		return IdentificationDocumentsPolicyValue::getApprovers($value);
	}

	public function userCanApproveValidationDocuments(?IUser $user, bool $throw = true): bool {
		if ($user === null) {
			return false;
		}

		$authorized = $this->getApprovalGroups($user);
		if (empty($authorized)) {
			$authorized = ApprovalGroupsPolicyValue::DEFAULT_GROUPS;
		}

		$userGroups = $this->groupManager->getUserGroupIds($user);
		if (!array_intersect($userGroups, $authorized)) {
			if ($throw) {
				throw new LibresignException($this->l10n->t('You are not allowed to approve user profile documents.'));
			}
			return false;
		}

		return true;
	}

	/** @return list<string> */
	public function getApprovalGroups(?IUser $user = null): array {
		$resolved = $user
			? $this->policyService->resolveForUser(ApprovalGroupsPolicy::KEY, $user)
			: $this->policyService->resolve(ApprovalGroupsPolicy::KEY);

		return ApprovalGroupsPolicyValue::decode($resolved->getEffectiveValue());
	}

	/** @return array{enabled: bool, approvers: list<string>}|null */
	private function getSnapshotValue(?SignRequest $signRequest = null): ?array {
		$file = $this->getFileFromSignRequest($signRequest);
		if (!$file instanceof FileEntity) {
			return null;
		}

		$metadata = $file->getMetadata() ?? [];
		$policySnapshot = $metadata['policy_snapshot'] ?? null;
		if (!is_array($policySnapshot)) {
			return null;
		}

		$entry = $policySnapshot[IdentificationDocumentsPolicy::KEY] ?? null;
		if (!is_array($entry) || !array_key_exists('effectiveValue', $entry)) {
			return null;
		}

		return IdentificationDocumentsPolicyValue::normalize($entry['effectiveValue'], false);
	}

	private function getFileFromSignRequest(?SignRequest $signRequest = null): ?FileEntity {
		if (!$signRequest instanceof SignRequest) {
			return null;
		}

		$fileId = $signRequest->getFileId();
		if ($fileId === null) {
			return null;
		}

		try {
			return $this->fileMapper->getById($fileId);
		} catch (\Throwable) {
			return null;
		}
	}

	private function getRelatedSignRequest(IdDocs $idDocs): ?SignRequest {
		$signRequestId = $idDocs->getSignRequestId();
		if ($signRequestId === null) {
			return null;
		}

		try {
			return $this->signRequestMapper->getById($signRequestId);
		} catch (\Throwable) {
			return null;
		}
	}
}
