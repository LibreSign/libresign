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

	/**
	 * @throws LibresignException
	 */
	private function loadEntitiesFromUuid(string $uuid): void {
		if ($this->signRequestEntity instanceof SignRequestEntity
			&& $this->fileEntity instanceof FileEntity) {
			return;
		}
		try {
			$this->signRequestEntity = $this->signFileService->getSignRequestByUuid($uuid);
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
	}

	/**
	 * @throws LibresignException
	 */
	public function validateRenewSigner(string $uuid): void {
		$this->loadEntitiesFromUuid($uuid);
		$this->signFileService->validateRenewSigner($uuid, $this->userSession->getUser());
	}

	/**
	 * @throws LibresignException
	 */
	public function loadNextcloudFileFromSignRequestUuid(string $uuid): void {
		$this->loadEntitiesFromUuid($uuid);
	}

	/**
	 * Load identification document approval entities from uuid resolver result
	 * Used when an approver is signing an identification document
	 *
	 * @param array $resolution Contains: 'file' => File entity, 'signRequest' => null, 'type' => 'id_doc'
	 * @throws LibresignException
	 */
	public function loadIdDocApprovalFromResolution(array $resolution): void {
		if ($resolution['type'] !== 'id_doc') {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [['message' => $this->l10n->t('Invalid id-doc request')]],
			]), AppFrameworkHttp::STATUS_BAD_REQUEST);
		}

		if (!$resolution['file'] instanceof FileEntity) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [['message' => $this->l10n->t('Invalid file')]],
			]), AppFrameworkHttp::STATUS_NOT_FOUND);
		}

		$this->signRequestEntity = null;
		$this->fileEntity = $resolution['file'];
	}

	/**
	 * Load file entity from file UUID (no sign request context)
	 *
	 * @throws LibresignException
	 */
	public function loadFileFromUuid(string $uuid): void {
		try {
			$this->signRequestEntity = null;
			$this->fileEntity = $this->signFileService->getFileByUuid($uuid);
		} catch (DoesNotExistException|LibresignException) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [['message' => $this->l10n->t('Invalid UUID')]],
			]), AppFrameworkHttp::STATUS_NOT_FOUND);
		}
	}

	public function getSignRequestEntity(): ?SignRequestEntity {
		return $this->signRequestEntity;
	}

	public function getFileEntity(): ?FileEntity {
		return $this->fileEntity;
	}

	/**
	 * @return File[] Array of files, empty if no file entity loaded
	 */
	public function getNextcloudFiles(): array {
		if (!$this->fileEntity) {
			return [];
		}

		return $this->signFileService->getNextcloudFiles($this->fileEntity);
	}
}
