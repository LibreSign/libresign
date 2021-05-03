<?php

namespace OCA\Libresign\Service;

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

	/**
	 * Get folder for user
	 *
	 * @param int $nodeId
	 * @return Folder
	 */
	public function getFolder(int $nodeId = null) {
		if ($nodeId) {
			return $this->root->getById($nodeId)[0]->getParent();
		}
		$path = '/' . $this->userId . '/files/' . $this->getLibreSignDefaultPath();
		$path = str_replace('//', '/', $path);

		return $this->getOrCreateFolder($path);
	}

	/**
	 * Finds a folder and creates it if non-existent
	 * @param string $path path to the folder
	 *
	 * @return Folder
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function getOrCreateFolder($path) {
		if ($this->root->nodeExists($path)) {
			$folder = $this->root->get($path);
		} else {
			$folder = $this->root->newFolder($path);
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

	public function deleteParentNodeOfNodeId(int $nodeId) {
		$node = $this->root->getById($nodeId);
		if (count($node) < 1) {
			throw new \Exception('Invalid node');
		}
		$parent = $node[0]->getParent();
		$parent->delete();
	}
}
