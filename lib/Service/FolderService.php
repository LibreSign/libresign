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
		string $userId
	) {
		$this->root = $root;
		$this->config = $config;
		$this->l10n = $l10n;
		$this->userId = $userId;
	}
	/**
	 * @return Folder
	 */
	public function getFolderForUser() {
		$path = '/' . $this->userId . '/files/' . $this->getUserFolderPath();
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
	public function getUserFolderPath() {
		$path = $this->config->getUserValue($this->userId, 'libresign', 'folder');

		if (!$path) {
			$path = '/' . $this->l10n->t('LibreSign');
			$this->config->setUserValue($this->userId, 'libresign', 'folder', $path);
		}

		return $path;
	}
}
