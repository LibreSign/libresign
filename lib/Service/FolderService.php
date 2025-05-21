<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Exception\LibresignException;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IAppData;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;

class FolderService {
	protected IAppData $appData;
	public function __construct(
		private IRootFolder $root,
		protected IAppDataFactory $appDataFactory,
		protected IGroupManager $groupManager,
		private IAppConfig $appConfig,
		private IL10N $l10n,
		private ?string $userId,
	) {
		$this->userId = $userId;
		$this->appData = $appDataFactory->get('libresign');
	}

	public function setUserId(string $userId): void {
		$this->userId = $userId;
	}

	public function getUserId(): ?string {
		return $this->userId;
	}

	/**
	 * Get folder for user and creates it if non-existent
	 *
	 * @psalm-suppress MixedReturnStatement
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getFolder(): Folder {
		$path = $this->getLibreSignDefaultPath();
		$containerFolder = $this->getContainerFolder();
		if (!$containerFolder->nodeExists($path)) {
			return $containerFolder->newFolder($path);
		}
		/** @var Folder */
		return $containerFolder->get($path);
	}

	/**
	 * @throws NotFoundException
	 */
	public function getFileById(?int $nodeId = null): File {
		if ($this->getUserId()) {
			/** @var File[] */
			$file = $this->root->getUserFolder($this->getUserId())->getById($nodeId);
			if ($file) {
				if (!$file[0]->fopen('r')) {
					throw new NotFoundException('Invalid node');
				}
				return $file[0];
			}

			$folder = $this->root->getUserFolder($this->getUserId());
			/** @var File[] */
			$file = $folder->getById($nodeId);
			if ($file) {
				if (!$file[0]->fopen('r')) {
					throw new NotFoundException('Invalid node');
				}
				return current($file);
			}
		}
		$path = $this->getLibreSignDefaultPath();
		$containerFolder = $this->getContainerFolder();
		if (!$containerFolder->nodeExists($path)) {
			throw new NotFoundException('Invalid node');
		}
		/** @var Folder $folder */
		$folder = $containerFolder->get($path);
		$file = $folder->getById($nodeId);
		if (empty($file)) {
			throw new NotFoundException('Invalid node');
		}
		/** @var File */
		return current($file);
	}

	private function getContainerFolder(): Folder {
		$withoutPermission = false;
		if ($this->getUserId()) {
			$containerFolder = $this->root->getUserFolder($this->getUserId());
			// TODO: retrieve guest group name from app once exposed
			if ($this->groupManager->isInGroup($this->getUserId(), 'guest_app')) {
				$withoutPermission = true;
			} elseif (!$containerFolder->isUpdateable()) {
				$withoutPermission = true;
			}
		} else {
			$withoutPermission = true;
		}
		if ($withoutPermission) {
			$containerFolder = $this->appData->getFolder('/');
			$reflection = new \ReflectionClass($containerFolder);
			$reflectionProperty = $reflection->getProperty('folder');
			$reflectionProperty->setAccessible(true);
			return $reflectionProperty->getValue($containerFolder);
		}
		return $this->root->getUserFolder($this->getUserId());
	}

	private function getLibreSignDefaultPath(): string {
		if (!$this->userId) {
			return 'unauthenticated';
		}
		// TODO: retrieve guest group name from app once exposed
		if ($this->groupManager->isInGroup($this->getUserId(), 'guest_app')) {
			return 'guest_app/' . $this->getUserId();
		}
		$path = $this->appConfig->getUserValue($this->userId, 'folder');

		if (empty($path)) {
			$defaultFolder = $this->appConfig->getAppValueString('default_user_folder', 'LibreSign');
			$path = '/' . $defaultFolder;
			$this->appConfig->setUserValue($this->userId, 'folder', $path);
		}

		return $path;
	}

	/**
	 * @param array{settings: array, name: string} $data
	 * @param IUser $owner
	 */
	public function getFolderName(array $data, IUser $owner): string {
		if (isset($data['settings']['folderName'])) {
			return $data['settings']['folderName'];
		}

		if (!isset($data['settings']['folderPatterns'])) {
			$data['settings']['separator'] = '_';
			$data['settings']['folderPatterns'][] = [
				'name' => 'date',
				'setting' => 'Y-m-d\TH-i-s-u'
			];
			$data['settings']['folderPatterns'][] = [
				'name' => 'name'
			];
			$data['settings']['folderPatterns'][] = [
				'name' => 'userId'
			];
		}
		$folderName = null;
		foreach ($data['settings']['folderPatterns'] as $pattern) {
			switch ($pattern['name']) {
				case 'date':
					$folderName[] = (new \DateTime('NOW'))->format($pattern['setting']);
					break;
				case 'name':
					if (!empty($data['name'])) {
						$folderName[] = $data['name'];
					}
					break;
				case 'userId':
					$folderName[] = $owner->getUID();
					break;
			}
		}
		return implode($data['settings']['separator'], $folderName);
	}

	public function getFileByPath(string $path): Node {
		$userFolder = $this->root->getUserFolder($this->getUserId());
		try {
			return $userFolder->get($path);
		} catch (NotFoundException) {
			throw new LibresignException($this->l10n->t('Invalid data to validate file'), 404);
		}
	}
}
