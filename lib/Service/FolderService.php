<?php

namespace OCA\Libresign\Service;

class FolderService {

	/**
	 * @return Folder
	 */
	public function getFolderForUser() {
		$path = '/' . $this->user_id . '/files/' . $this->getUserFolderPath();
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
		$path = $this->config->getUserValue($this->user_id, 'libresign', 'folder');

		if (!$path) {
			$path = '/' . $this->il10n->t('LibreSign');
			$this->config->setUserValue($this->user_id, 'libresign', 'folder', $path);
		}

		return $path;
	}
}