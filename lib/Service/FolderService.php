<?php

namespace OCA\Libresign\Service;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;

class FolderService {
	/** @var IRootFolder */
	private $root;
	/** @var IConfig */
	private $config;
	/** @var IL10N */
	private $l10n;
	/** @var string */
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

	public function setUserId($userId) {
		$this->userId = $userId;
	}

	public function getUserId() {
		return $this->userId;
	}

	/**
	 * Get folder for user
	 *
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
	 * @return Folder
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function getOrCreateFolder() {
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
	 * @return string
	 */
	public function getLibreSignDefaultPath() {
		$path = $this->config->getUserValue($this->userId, 'libresign', 'folder');

		if (!$path) {
			$path = '/' . $this->l10n->t('LibreSign');
			$this->config->setUserValue($this->userId, 'libresign', 'folder', $path);
		}

		return $path;
	}
}
