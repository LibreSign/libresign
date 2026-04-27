<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicy;
use OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicyValue;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUser;

class IdDocsPolicyService {
	public function __construct(
		private PolicyService $policyService,
		private ValidateHelper $validateHelper,
		private IdDocsMapper $idDocsMapper,
	) {
	}

	public function canApproverSignIdDoc(IUser $user, int $fileId, int $status): bool {
		if (!$this->isIdentificationDocumentsEnabled($user)) {
			return false;
		}
		if (!$this->validateHelper->userCanApproveValidationDocuments($user, false)) {
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

	private function isIdentificationDocumentsEnabled(IUser $user): bool {
		$value = $this->policyService->resolveForUser(IdentificationDocumentsPolicy::KEY, $user)->getEffectiveValue();
		return IdentificationDocumentsPolicyValue::normalize($value, false);
	}
}
