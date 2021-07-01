<?php

namespace OCA\Libresign\Helper;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileUserMapper;
use OCA\LibreSign\Db\File as LibresignFile;
use OCA\Libresign\Db\FileMapper;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;

class ValidateHelper {
	/** @var IL10N */
	private $l10n;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var FileMapper */
	private $fileMapper;
	/** @var IConfig */
	private $config;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IRootFolder */
	private $root;
	/** @var LibresignFile */
	private $libresignFile;

	public function __construct(
		IL10N $l10n,
		FileUserMapper $fileUserMapper,
		FileMapper $fileMapper,
		IConfig $config,
		IGroupManager $groupManager,
		IRootFolder $root
	) {
		$this->l10n = $l10n;
		$this->fileUserMapper = $fileUserMapper;
		$this->fileMapper = $fileMapper;
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->root = $root;
	}
	public function validateFile(array $data) {
		if (empty($data['file'])) {
			throw new \Exception($this->l10n->t('Empty file'));
		}
		if (empty($data['file']['url']) && empty($data['file']['base64']) && empty($data['file']['fileId'])) {
			throw new \Exception($this->l10n->t('Inform URL or base64 or fileID to sign'));
		}
		if (!empty($data['file']['fileId'])) {
			if (!is_numeric($data['file']['fileId'])) {
				throw new \Exception($this->l10n->t('Invalid fileID'));
			}
			$this->validateNotRequestedSign((int)$data['file']['fileId']);
			$this->validateFileByNodeId((int)$data['file']['fileId']);
		}
		if (!empty($data['file']['base64'])) {
			$input = base64_decode($data['file']['base64']);
			$base64 = base64_encode($input);
			if ($data['file']['base64'] !== $base64) {
				throw new \Exception($this->l10n->t('Invalid base64 file'));
			}
		}
	}

	public function validateNotRequestedSign(int $nodeId) {
		try {
			$fileMapper = $this->fileUserMapper->getByNodeId($nodeId);
		} catch (\Throwable $th) {
		}
		if (!empty($fileMapper)) {
			throw new \Exception($this->l10n->t('Already asked to sign this document'));
		}
	}

	public function validateFileByNodeId(int $nodeId) {
		try {
			$file = $this->getFileById($nodeId);
		} catch (\Throwable $th) {
			throw new \Exception($this->l10n->t('Invalid fileID'));
		}
		if ($file->getMimeType() !== 'application/pdf') {
			throw new \Exception($this->l10n->t('Must be a fileID of a PDF'));
		}
	}

	private function getFileById(int $nodeId): \OCP\Files\File {
		if (empty($this->file)) {
			$libresignFile = $this->getLibreSignFileByNodeId($nodeId);

			$userFolder = $this->root->getUserFolder($libresignFile->getUserId());
			$this->file = $userFolder->getById($nodeId);
			if (!empty($this->file)) {
				$this->file = $this->file[0];
			}
		}
		return $this->file;
	}

	private function getLibreSignFileByNodeId(int $nodeId): LibresignFile {
		if (empty($this->libresignFile)) {
			$this->libresignFile = $this->fileMapper->getByFileId($nodeId);
		}
		return $this->libresignFile;
	}

	public function canRequestSign(IUser $user) {
		$authorized = json_decode($this->config->getAppValue(Application::APP_ID, 'webhook_authorized', '["admin"]'));
		if (empty($authorized) || !is_array($authorized)) {
			throw new \Exception($this->l10n->t('You are not allowed to request signing'));
		}
		$userGroups = $this->groupManager->getUserGroupIds($user);
		if (!array_intersect($userGroups, $authorized)) {
			throw new \Exception($this->l10n->t('You are not allowed to request signing'));
		}
	}

	public function iRequestedSignThisFile(IUser $user, int $nodeId) {
		$libresignFile = $this->getLibreSignFileByNodeId($nodeId);
		if ($libresignFile->getUserId() !== $user->getUID()) {
			throw new \Exception($this->l10n->t('You are not the signer request for this file'));
		}
	}
}
