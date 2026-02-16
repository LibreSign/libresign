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

	public function setUserId(?string $userId): void {
		$this->userId = $userId;
	}

	public function getUserId(): ?string {
		return $this->userId;
	}

	/**
	 * Get the user's root folder (full home), not the LibreSign container.
	 *
	 * @throws LibresignException
	 */
	public function getUserRootFolder(): Folder {
		if (!$this->userId) {
			throw new LibresignException('Invalid user to resolve folder');
		}

		return $this->root->getUserFolder($this->userId);
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
	public function getFileByNodeId(int $nodeId): File {
		// For guests, files are stored in appdata, not in user folder
		// Skip getUserFolder search for guests to avoid false positives
		if ($this->getUserId() && !$this->groupManager->isInGroup($this->getUserId(), 'guest_app')) {
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
	 * Get or create the folder where a file should be stored
	 *
	 * @param array $data Must contain 'settings' and optionally 'name', 'userManager'
	 * @param mixed $identifier User or string identifier
	 * @return Folder The folder where files should be created
	 * @throws LibresignException
	 */
	public function getFolderForFile(array $data, $identifier): Folder {
		$userFolder = $this->getFolder();

		if (isset($data['settings']['envelopeFolderId'])) {
			$envelopeFolder = $userFolder->getFirstNodeById($data['settings']['envelopeFolderId']);
			if ($envelopeFolder === null || !$envelopeFolder instanceof Folder) {
				throw new LibresignException($this->l10n->t('Envelope folder not found'));
			}
			return $envelopeFolder;
		}

		$folderName = $this->getFolderName($data, $identifier);
		return $userFolder->newFolder($folderName);
	}

	/**
	 * @param array{settings: array, name: string} $data
	 * @param IUser $owner
	 */
	public function getFolderName(array $data, $identifier): string {
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
		$folderName = [];
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
					if ($identifier instanceof \OCP\IUser) {
						$folderName[] = $identifier->getUID();
					} elseif (!empty($identifier) && is_string($identifier)) {
						$folderName[] = $identifier;
					}
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

	/**
	 * Ensure a folder exists at a given absolute user path, creating missing segments.
	 * If the final folder already exists, it must be empty.
	 *
	 * @throws LibresignException
	 */
	public function getOrCreateFolderByAbsolutePath(string $path): Folder {
		if (!$this->userId) {
			throw new LibresignException('Invalid user to create envelope folder');
		}

		$cleanPath = ltrim($path, '/');
		$userFolder = $this->root->getUserFolder($this->userId);

		if ($cleanPath === '') {
			return $userFolder;
		}

		$segments = array_filter(explode('/', $cleanPath), static fn (string $segment) => $segment !== '');
		$folder = $userFolder;
		$isLastSegment = false;

		foreach ($segments as $index => $segment) {
			$isLastSegment = ($index === count($segments) - 1);

			try {
				$node = $folder->get($segment);
				if (!$node instanceof Folder) {
					throw new LibresignException('Invalid folder path');
				}
				$folder = $node;

				if ($isLastSegment) {
					$contents = $folder->getDirectoryListing();
					if (count($contents) > 0) {
						throw new LibresignException($this->l10n->t('Folder already exists and is not empty: %s', [$path]));
					}
				}
			} catch (NotFoundException) {
				$folder = $folder->newFolder($segment);
			}
		}

		return $folder;
	}
}
