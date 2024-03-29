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

use OCA\Libresign\Exception\LibresignException;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\Config\IUserMountCache;
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
		private IUserMountCache $userMountCache,
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
	 * Get folder for user
	 *
	 * @psalm-suppress MixedReturnStatement
	 * @psalm-suppress InvalidReturnStatement
	 * @psalm-suppress MixedMethodCall
	 */
	public function getFolder(int $nodeId = null): Folder {
		if ($nodeId) {
			$mountsContainingFile = $this->userMountCache->getMountsForFileId($nodeId);
			foreach ($mountsContainingFile as $fileInfo) {
				$this->root->getByIdInPath($nodeId, $fileInfo->getMountPoint());
			}
			$node = $this->root->getById($nodeId);
			if (!$node) {
				throw new \Exception('Invalid node');
			}
			return $node[0]->getParent();
		}

		return $this->getOrCreateFolder();
	}

	/**
	 * Finds a folder and creates it if non-existent
	 *
	 * @psalm-suppress MixedReturnStatement
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function getOrCreateFolder(): Folder {
		$path = $this->getLibreSignDefaultPath();
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
			$containerFolder = $reflectionProperty->getValue($containerFolder);
		} else {
			$containerFolder = $this->root->getUserFolder($this->getUserId());
		}
		if ($containerFolder->nodeExists($path)) {
			$folder = $containerFolder->get($path);
		} else {
			$folder = $containerFolder->newFolder($path);
		}
		return $folder;
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 */
	public function getLibreSignDefaultPath(): string {
		if (!$this->userId) {
			return 'unauthenticated';
		}
		// TODO: retrieve guest group name from app once exposed
		if ($this->groupManager->isInGroup($this->getUserId(), 'guest_app')) {
			return 'guest_app/' . $this->getUserId();
		}
		$path = $this->appConfig->getUserValue($this->userId, 'folder');

		if (empty($path)) {
			$defaultFolder = $this->appConfig->getAppValue('default_user_folder', 'LibreSign');
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
				'setting' => 'Y-m-d\TH:i:s'
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
		} catch (NotFoundException $e) {
			throw new LibresignException($this->l10n->t('Invalid data to validate file'), 404);
		}
	}
}
