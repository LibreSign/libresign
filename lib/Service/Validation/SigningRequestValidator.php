<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Validation;

use OC\AppFramework\Http;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\Policy\RequestSignAuthorizationService;
use OCP\IL10N;
use OCP\IUser;

class SigningRequestValidator {
	public function __construct(
		private IL10N $l10n,
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private RequestSignAuthorizationService $requestSignAuthorizationService,
		private FileInputValidator $fileInputValidator,
	) {
	}

	public function validateWorkflowIsNotClosedByFileId(int $fileId): void {
		try {
			$file = $this->fileMapper->getById($fileId);
		} catch (\Throwable) {
			return;
		}
		$this->validateWorkflowIsNotClosed($file);
	}

	public function validateWorkflowIsNotClosedByUuid(string $uuid): void {
		try {
			$file = $this->fileMapper->getByUuid($uuid);
		} catch (\Throwable) {
			return;
		}
		$this->validateWorkflowIsNotClosed($file);
	}

	public function validateWorkflowIsNotClosed(File $file): void {
		if ($file->getStatus() === FileStatus::CANCELED->value) {
			throw new LibresignException($this->l10n->t('The signing workflow of this document is already closed.'));
		}
	}

	public function fileCanBeSigned(File $file): void {
		$this->validateWorkflowIsNotClosed($file);
		if (!in_array($file->getStatus(), [FileStatus::ABLE_TO_SIGN->value, FileStatus::PARTIAL_SIGNED->value], true)) {
			$statusText = $this->fileMapper->getTextOfStatus($file->getStatus());
			throw new LibresignException($this->l10n->t('This file cannot be signed. Invalid status: %s', $statusText));
		}
	}

	public function canRequestSign(IUser $user): void {
		if (!$this->requestSignAuthorizationService->canRequestSign($user)) {
			throw new LibresignException(
				json_encode([
					'action' => JSActions::ACTION_DO_NOTHING,
					'errors' => [['message' => $this->l10n->t('You are not allowed to create signature requests')]],
				]),
				Http::STATUS_UNPROCESSABLE_ENTITY,
			);
		}
	}

	public function iRequestedSignThisFile(IUser $user, int $fileId): void {
		$libresignFile = $this->fileMapper->getById($fileId);
		if ($libresignFile->getUserId() !== $user->getUID()) {
			throw new LibresignException($this->l10n->t('You do not have permission for this action.'));
		}
	}

	public function validateFileStatus(array $data): void {
		if (!array_key_exists('status', $data)) {
			return;
		}
		$validStatusList = [FileStatus::DRAFT->value, FileStatus::ABLE_TO_SIGN->value, FileStatus::DELETED->value];
		if (!in_array($data['status'], $validStatusList, true)) {
			throw new LibresignException($this->l10n->t('Invalid status code for file.'));
		}

		$file = null;
		if (!empty($data['uuid'])) {
			$file = $this->fileMapper->getByUuid($data['uuid']);
		} elseif (!empty($data['file']['fileId'])) {
			try {
				$file = $this->fileMapper->getById($data['file']['fileId']);
			} catch (\Throwable) {
			}
		}
		if ($file instanceof File) {
			if ($data['status'] > $file->getStatus() && $file->getStatus() >= FileStatus::ABLE_TO_SIGN->value && $data['status'] !== FileStatus::DELETED->value) {
				throw new LibresignException($this->l10n->t('Sign process already started. Unable to change status.'));
			}
		} elseif ($data['status'] === FileStatus::DELETED->value) {
			throw new LibresignException($this->l10n->t('Invalid status code for file.'));
		}
	}

	public function validateExistingFile(array $data): void {
		if (isset($data['uuid'])) {
			$this->validateFileUuid($data);
			$file = $this->fileMapper->getByUuid($data['uuid']);
			$this->iRequestedSignThisFile($data['userManager'], $file->getId());
			return;
		}
		if (isset($data['file'])) {
			if (!isset($data['file']['fileId'])) {
				throw new LibresignException($this->l10n->t('Invalid fileID'));
			}
			$this->fileInputValidator->validateLibreSignFileId($data['file']['fileId']);
			$this->iRequestedSignThisFile($data['userManager'], $data['file']['fileId']);
			return;
		}
		throw new LibresignException($this->l10n->t('Please provide either UUID or File object'));
	}

	public function validateFileUuid(array $data): void {
		try {
			$this->fileMapper->getByUuid($data['uuid']);
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('Invalid UUID file'));
		}
	}

	public function validateIsSignerOfFile(int $signRequestId, int $fileId): void {
		try {
			$this->signRequestMapper->getByFileIdAndSignRequestId($fileId, $signRequestId);
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('Signer not associated to this file'));
		}
	}
}
