<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;

class FolderService {
	public function __construct(
		private IRootFolder $root,
		private IConfig $config,
		private IL10N $l10n,
		private ?string $userId
	) {
		$this->userId = $userId;
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
			$userFolder = $this->root->getUserFolder($this->getUserId());
			$node = $userFolder->getById($nodeId);
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
		$userFolder = $this->root->getUserFolder($this->getUserId());
		if ($userFolder->nodeExists($path)) {
			$folder = $userFolder->get($path);
		} else {
			$folder = $userFolder->newFolder($path);
		}
		return $folder;
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 */
	public function getLibreSignDefaultPath(): string {
		$path = $this->config->getUserValue($this->userId, 'libresign', 'folder');

		if (empty($path)) {
			$defaultFolder = $this->config->getAppValue(Application::APP_ID, 'default_user_folder', 'LibreSign');
			$path = '/' . $defaultFolder;
			$this->config->setUserValue($this->userId, 'libresign', 'folder', $path);
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
}
