<?php

namespace OCA\Libresign\Helper;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Service\FolderService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;

class ValidateHelper {
	/** @var IL10N */
	private $l10n;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var FolderService */
	private $folderService;
	/** @var IConfig */
	private $config;
	/** @var IGroupManager */
	private $groupManager;

	public function __construct(
		IL10N $l10n,
		FileUserMapper $fileUserMapper,
		FolderService $folderService,
		IConfig $config,
		IGroupManager $groupManager
	) {
		$this->l10n = $l10n;
		$this->fileUserMapper = $fileUserMapper;
		$this->folderService = $folderService;
		$this->config = $config;
		$this->groupManager = $groupManager;
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

	public function validateFileByNodeId(int $nodeId) {
		try {
			$fileMapper = $this->fileUserMapper->getByNodeId($nodeId);
		} catch (\Throwable $th) {
		}
		if (!empty($fileMapper)) {
			throw new \Exception($this->l10n->t('Already asked to sign this document'));
		}

		try {
			$userFolder = $this->folderService->getFolder($nodeId);
			$node = $userFolder->getById($nodeId);
		} catch (\Throwable $th) {
			throw new \Exception($this->l10n->t('Invalid fileID'));
		}
		if (!$node) {
			throw new \Exception($this->l10n->t('Invalid fileID'));
		}
		$node = $node[0];
		if ($node->getMimeType() !== 'application/pdf') {
			throw new \Exception($this->l10n->t('Must be a fileID of a PDF'));
		}
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
}
