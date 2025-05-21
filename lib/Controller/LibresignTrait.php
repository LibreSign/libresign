<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OC\AppFramework\Http as AppFrameworkHttp;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\File;
use OCP\IL10N;
use OCP\IUserSession;

trait LibresignTrait {
	protected SignFileService $signFileService;
	protected IL10N $l10n;
	protected IUserSession $userSession;
	private ?SignRequestEntity $signRequestEntity = null;
	private ?FileEntity $fileEntity = null;
	private ?File $nextcloudFile = null;

	/**
	 * @throws LibresignException
	 */
	private function loadEntitiesFromUuid(string $uuid): void {
		if ($this->signRequestEntity instanceof SignRequestEntity
			&& $this->fileEntity instanceof FileEntity) {
			return;
		}
		try {
			$this->signRequestEntity = $this->signFileService->getSignRequest($uuid);
			$this->fileEntity = $this->signFileService->getFile(
				$this->signRequestEntity->getFileId(),
			);
		} catch (DoesNotExistException|LibresignException) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [['message' => $this->l10n->t('Invalid UUID')]],
			]), AppFrameworkHttp::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @throws LibresignException
	 */
	public function validateSignRequestUuid(string $uuid): void {
		$this->loadEntitiesFromUuid($uuid);
		$this->signFileService->validateSigner($uuid, $this->userSession->getUser());
		$this->nextcloudFile = $this->signFileService->getNextcloudFile($this->fileEntity);
	}

	/**
	 * @throws LibresignException
	 */
	public function validateRenewSigner(string $uuid): void {
		$this->loadEntitiesFromUuid($uuid);
		$this->signFileService->validateRenewSigner($uuid, $this->userSession->getUser());
		$this->nextcloudFile = $this->signFileService->getNextcloudFile($this->fileEntity);
	}

	/**
	 * @throws LibresignException
	 */
	public function loadNextcloudFileFromSignRequestUuid(string $uuid): void {
		$this->loadEntitiesFromUuid($uuid);
		$this->nextcloudFile = $this->signFileService->getNextcloudFile($this->fileEntity);
	}

	public function getSignRequestEntity(): ?SignRequestEntity {
		return $this->signRequestEntity;
	}

	public function getFileEntity(): ?FileEntity {
		return $this->fileEntity;
	}

	public function getNextcloudFile(): ?File {
		return $this->nextcloudFile;
	}
}
