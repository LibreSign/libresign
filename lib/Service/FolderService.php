<?php

namespace OCA\Libresign\Service;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;

class FolderService {
	/** @var IRootFolder */
	private $root;
	/** @var IConfig */
	private $config;
	/** @var IL10N */
	private $l10n;
	/** @var string|null */
	private $userId;

	public function __construct(
		IRootFolder $root,
		IConfig $config,
		IL10N $l10n,
		?string $userId
	) {
		$this->root = $root;
		$this->config = $config;
		$this->l10n = $l10n;
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
	 * @param int $nodeId
	 * @return Folder
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
	 *
	 * @return Folder
	 *
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
	 * @return string
	 */
	public function getLibreSignDefaultPath(): string {
		$path = $this->config->getUserValue($this->userId, 'libresign', 'folder');

		if (empty($path)) {
			$path = '/' . $this->l10n->t('LibreSign');
			$this->config->setUserValue($this->userId, 'libresign', 'folder', $path);
		}

		return $path;
	}

	/**
	 * @param array{settings: array, name: string} $data
	 * @param IUser $owner
	 */
	public function getFolderName(array $data, IUser $owner): string {
		if(isset($data['settings']['folderName'])) {
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
