<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\IdDocsMapper;
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
	) {
	}

	public function canApproverSignIdDoc(IUser $user, int $fileId, int $status): bool {
		if (!$this->isIdentificationDocumentsEnabled($user)) {
			return false;
		}
		if (!$this->userCanApproveValidationDocuments($user, false)) {
			return false;
		}
		$readyStatuses = [FileStatus::ABLE_TO_SIGN->value, FileStatus::PARTIAL_SIGNED->value];
		if (!in_array($status, $readyStatuses, true)) {
			return false;
		}
		try {
			$this->idDocsMapper->getByFileId($fileId);
			return true;
		} catch (DoesNotExistException) {
			return false;
		}
	}

	public function isIdentificationDocumentsEnabled(?IUser $user = null): bool {
		$resolved = $user
			? $this->policyService->resolveForUser(IdentificationDocumentsPolicy::KEY, $user)
			: $this->policyService->resolve(IdentificationDocumentsPolicy::KEY);
		$value = $resolved->getEffectiveValue();
		return IdentificationDocumentsPolicyValue::normalize($value, false);
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
}
