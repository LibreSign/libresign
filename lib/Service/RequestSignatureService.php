<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
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

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUser as FileUserEntity;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\Node;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Sabre\DAV\UUIDUtil;

class RequestSignatureService {
	use TFile;

	public function __construct(
		protected IL10N $l10n,
		protected SignMethodService $signMethod,
		protected IdentifyMethodService $identifyMethod,
		protected FileUserMapper $fileUserMapper,
		protected IUserManager $userManager,
		protected FileMapper $fileMapper,
		protected IdentifyMethodMapper $identifyMethodMapper,
		protected PdfParserService $pdfParserService,
		protected FileElementService $fileElementService,
		protected FileElementMapper $fileElementMapper,
		protected FolderService $folderService,
		protected IMimeTypeDetector $mimeTypeDetector,
		protected ValidateHelper $validateHelper,
		protected IClientService $client,
		protected LoggerInterface $logger
	) {
	}

	public function save(array $data): array {
		$file = $this->saveFile($data);
		$this->saveVisibleElements($data, $file);
		$return['uuid'] = $file->getUuid();
		$return['nodeId'] = $file->getNodeId();
		$return['users'] = $this->associateToSigners($data, $file->getId());
		return $return;
	}

	/**
	 * Save file data
	 *
	 * @param array{userManager: IUser, name: string, callback: string} $data
	 */
	public function saveFile(array $data): FileEntity {
		if (!empty($data['uuid'])) {
			return $this->fileMapper->getByUuid($data['uuid']);
		}
		$fileId = null;
		if (isset($data['file']['fileNode']) && $data['file']['fileNode'] instanceof Node) {
			$fileId = $data['file']['fileNode']->getId();
		} elseif (!empty($data['file']['fileId'])) {
			$fileId = $data['file']['fileId'];
		}
		if (!is_null($fileId)) {
			try {
				$file = $this->fileMapper->getByFileId($fileId);
				if (!empty($data['status']) && $data['status'] > $file->getStatus()) {
					$file->setStatus($data['status']);
					return $this->fileMapper->update($file);
				}
				return $file;
			} catch (\Throwable $th) {
			}
		}

		$node = $this->getNodeFromData($data);

		$file = new FileEntity();
		$file->setNodeId($node->getId());
		$file->setUserId($data['userManager']->getUID());
		$file->setUuid(UUIDUtil::getUUID());
		$file->setCreatedAt(time());
		$file->setName($data['name']);
		$file->setMetadata(json_encode($this->getFileMetadata($node)));
		if (!empty($data['callback'])) {
			$file->setCallback($data['callback']);
		}
		if (isset($data['status'])) {
			$file->setStatus($data['status']);
		} else {
			$file->setStatus(FileEntity::STATUS_ABLE_TO_SIGN);
		}
		$this->fileMapper->insert($file);
		return $file;
	}

	public function getFileMetadata(\OCP\Files\Node $node): array {
		$metadata = [
			'extension' => $node->getExtension(),
		];
		if ($metadata['extension'] === 'pdf') {
			$metadata = $this->pdfParserService->getMetadata($node);
		}
		return $metadata;
	}

	/**
	 * @return FileUserEntity[]
	 *
	 * @psalm-return list<FileUserEntity>
	 */
	private function associateToSigners(array $data, int $fileId): array {
		$return = [];
		if (!empty($data['users'])) {
			foreach ($data['users'] as $user) {
				if (!array_key_exists('identify', $user)) {
					throw new \Exception('Identify key not found');
				}
				$identifyMethods = $this->identifyMethod->getByUserData($user['identify'], $fileId);
				$fileUser = $this->getFileUserByIdentifyMethod(
					current($identifyMethods),
					$fileId
				);
				$this->setDataToUser($fileUser, $user, $fileId);
				$this->saveFileUser($fileUser);
				foreach ($identifyMethods as $identifyMethod) {
					$identifyMethod->getEntity()->setFileUserId($fileUser->getId());
					$identifyMethod->willNotifyUser(isset($user['notify']) ? $user['notify'] : true);
					$identifyMethod->save();
				}
				$return[] = $fileUser;
			}
		}
		return $return;
	}

	private function saveVisibleElements(array $data, FileEntity $file): array {
		if (empty($data['visibleElements'])) {
			return [];
		}
		$elements = $data['visibleElements'];
		foreach ($elements as $key => $element) {
			$element['fileId'] = $file->getId();
			$elements[$key] = $this->fileElementService->saveVisibleElement($element);
		}
		return $elements;
	}

	public function validateNewRequestToFile(array $data): void {
		$this->validateNewFile($data);
		$this->validateUsers($data);
		$this->validateHelper->validateFileStatus($data);
	}

	public function validateNewFile(array $data): void {
		if (empty($data['name'])) {
			throw new \Exception($this->l10n->t('Name is mandatory'));
		}
		$this->validateHelper->validateNewFile($data);
	}

	public function validateUsers(array $data): void {
		if (empty($data['users'])) {
			throw new \Exception($this->l10n->t('Empty users list'));
		}
		if (!is_array($data['users'])) {
			// TRANSLATION This message will be displayed when the request to API with the key users has a value that is not an array
			throw new \Exception($this->l10n->t('User list needs to be an array'));
		}
		foreach ($data['users'] as $user) {
			$this->identifyMethod->setAllEntityData($user);
		}
	}

	public function saveFileUser(FileUserEntity $fileUser): void {
		if ($fileUser->getId()) {
			$this->fileUserMapper->update($fileUser);
		} else {
			$this->fileUserMapper->insert($fileUser);
		}
	}

	/**
	 * @psalm-suppress MixedMethodCall
	 */
	private function setDataToUser(FileUserEntity $fileUser, array $user, int $fileId): void {
		$fileUser->setFileId($fileId);
		if (!$fileUser->getUuid()) {
			$fileUser->setUuid(UUIDUtil::getUUID());
		}
		if (!empty($user['displayName'])) {
			$fileUser->setDisplayName($user['displayName']);
		}
		if (!empty($user['description'])) {
			$fileUser->setDescription($user['description']);
		}
		if (!$fileUser->getId()) {
			$fileUser->setCreatedAt(time());
		}
	}

	private function getFileUserByIdentifyMethod(IIdentifyMethod $identifyMethod, int $fileId): FileUserEntity {
		try {
			$fileUser = $this->fileUserMapper->getByIdentifyMethodAndFileId($identifyMethod, $fileId);
		} catch (DoesNotExistException $e) {
			$fileUser = new FileUserEntity();
		}
		return $fileUser;
	}

	public function unassociateToUser(int $fileId, int $fileUserId): void {
		$fileUser = $this->fileUserMapper->getByFileIdAndFileUserId($fileId, $fileUserId);
		try {
			$this->fileUserMapper->delete($fileUser);
			$visibleElements = $this->fileElementMapper->getByFileIdAndUserId($fileId, $fileUser->getUserId());
			foreach ($visibleElements as $visibleElement) {
				$this->fileElementMapper->delete($visibleElement);
			}
		} catch (\Throwable $th) {
		}
	}

	public function deleteRequestSignature(array $data): void {
		if (!empty($data['uuid'])) {
			$signatures = $this->fileUserMapper->getByFileUuid($data['uuid']);
			$fileData = $this->fileMapper->getByUuid($data['uuid']);
		} elseif (!empty($data['file']['fileId'])) {
			$signatures = $this->fileUserMapper->getByNodeId($data['file']['fileId']);
			$fileData = $this->fileMapper->getByFileId($data['file']['fileId']);
		} else {
			throw new \Exception($this->l10n->t('Inform or UUID or a File object'));
		}
		foreach ($signatures as $fileUser) {
			$this->fileUserMapper->delete($fileUser);
		}
		$this->fileMapper->delete($fileData);
		$this->fileElementService->deleteVisibleElements($fileData->getId());
	}
}
