<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
		} catch (DoesNotExistException|LibresignException $e) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->l10n->t('Invalid UUID')],
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
