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
use OCP\Lock\LockedException;

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
	 * @throws LockedException
	 */
	public function getFolder(): Folder {
		$path = $this->getLibreSignDefaultPath();
		$containerFolder = $this->getContainerFolder();
		try {
			/** @var Folder $folder */
			$folder = $containerFolder->get($path);
		} catch (NotFoundException) {
			/** @var Folder $folder */
			$folder = $containerFolder->newFolder($path);
		}
		return $folder;
	}

	/**
	 * @throws NotFoundException
	 */
	public function getFileById(?int $nodeId = null): File {
		if ($this->getUserId()) {

			$file = $this->root->getUserFolder($this->getUserId())->getFirstNodeById($nodeId);
			if ($file instanceof File) {
				return $file;
			}
		}
		$path = $this->getLibreSignDefaultPath();
		$containerFolder = $this->getContainerFolder();
		try {
			/** @var Folder $folder */
			$folder = $containerFolder->get($path);
		} catch (NotFoundException) {
			throw new NotFoundException('Invalid node');
		}
		$file = $folder->getFirstNodeById($nodeId);
		if (!$file instanceof File) {
			throw new NotFoundException('Invalid node');
		}
		return $file;
	}

	protected function getContainerFolder(): Folder {
		if ($this->getUserId() && !$this->groupManager->isInGroup($this->getUserId(), 'guest_app')) {
			$containerFolder = $this->root->getUserFolder($this->getUserId());
			if ($containerFolder->isUpdateable()) {
				return $containerFolder;
			}
		}
		$containerFolder = $this->appData->getFolder('/');
		$reflection = new \ReflectionClass($containerFolder);
		$reflectionProperty = $reflection->getProperty('folder');
		return $reflectionProperty->getValue($containerFolder);
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
					$folderName[] = (new \DateTime('now', new \DateTimeZone('UTC')))->format($pattern['setting']);
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
