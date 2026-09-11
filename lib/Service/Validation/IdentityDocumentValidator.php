<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Validation;

use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\IdDocsPolicyService;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;

class IdentityDocumentValidator {
	public function __construct(
		private IL10N $l10n,
		private IdDocsMapper $idDocsMapper,
		private FileTypeMapper $fileTypeMapper,
		private IdDocsPolicyService $idDocsPolicyService,
		private IUserManager $userManager,
	) {
	}

	public function validateIdDocIsOwnedByUser(int $nodeId, string $uid): void {
		try {
			$this->idDocsMapper->getByUserIdAndNodeId($uid, $nodeId);
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('This file is not yours'));
		}
	}

	public function validateIdDocBelongsToSignRequest(int $nodeId, int $signRequestId): void {
		try {
			$this->idDocsMapper->getBySignRequestIdAndNodeId($signRequestId, $nodeId);
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('Not allowed'));
		}
	}

	public function validateUserHasNoFileWithThisType(string $uid, string $type): void {
		if ($this->idDocsMapper->getByUserAndType($uid, $type) !== null) {
			throw new LibresignException($this->l10n->t('A file of this type has been associated.'));
		}
	}

	public function canSignWithIdentificationDocumentStatus(?IUser $user, int $status): void {
		if ($user && $this->userCanApproveValidationDocuments($user, false)) {
			return;
		}
		$allowedStatus = [FileService::IDENTIFICATION_DOCUMENTS_DISABLED, FileService::IDENTIFICATION_DOCUMENTS_APPROVED];
		if (!in_array($status, $allowedStatus, true)) {
			throw new LibresignException(
				$this->l10n->t('You need to have an approved identification document to sign.'),
				JSActions::ACTION_SIGN_ID_DOC,
			);
		}
	}

	public function validateFileTypeExists(string $type): void {
		if (!array_key_exists($type, $this->fileTypeMapper->getTypes())) {
			throw new LibresignException($this->l10n->t('Invalid file type.'));
		}
	}

	public function userCanApproveValidationDocuments(?IUser $user, bool $throw = true): bool {
		return $this->idDocsPolicyService->userCanApproveValidationDocuments($user, $throw);
	}

	public function haveValidMail(array $data, ?int $type = null): void {
		if ($type === FileInputValidator::TYPE_TO_SIGN) {
			return;
		}
		if (empty($data)) {
			throw new LibresignException($this->l10n->t('No user data'));
		}
		if (empty($data['email'])) {
			if (!empty($data['uid'])) {
				$user = $this->userManager->get($data['uid']);
				if (!$user) {
					throw new LibresignException($this->l10n->t('User not found.'));
				}
				if (!$user->getEMailAddress()) {
					throw new LibresignException($this->l10n->t('User %s has no email address.', [$data['uid']]));
				}
				return;
			}
			throw new LibresignException($this->l10n->t('Email required'));
		}
		if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			throw new LibresignException($this->l10n->t('Invalid email'));
		}
	}
}
