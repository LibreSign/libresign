<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUser as FileUserEntity;
use OCA\Libresign\Db\FileUserMapper;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;

class WebhookService {
	/** @var IConfig */
	private $config;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IL10N */
	private $l10n;
	/** @var FileMapper */
	private $fileMapper;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var FolderService */
	private $folderService;

	public function __construct(
		IConfig $config,
		IGroupManager $groupManager,
		IL10N $l10n,
		IRootFolder $rootFolder,
		FileMapper $fileMapper,
		FileUserMapper $fileUserMapper,
		FolderService $folderService
	) {
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->l10n = $l10n;
		$this->rootFolder = $rootFolder;
		$this->file = $fileMapper;
		$this->fileUser = $fileUserMapper;
		$this->folderService = $folderService;
	}

	public function validate(array $data) {
		$this->validateUserManager($data['userManager']);
		$this->validateFile($data);
		$this->validateUsers($data);
	}

	private function validateUserManager(IUser $user) {
		$authorized = json_decode($this->config->getAppValue(Application::APP_ID, 'webhook_authorized', '["admin"]'));
		if (!empty($authorized)) {
			$userGroups = $this->groupManager->getUserGroupIds($user);
			if (!array_intersect($userGroups, $authorized)) {
				throw new \Exception($this->l10n->t('Insufficient permissions to use API'));
			}
		}
	}

	private function validateFile($data) {
		if (empty($data['name'])) {
			throw new \Exception((string)$this->l10n->t('Name is mandatory'));
		}
		if (!preg_match('/^[\w \-_]+$/', $data['name'])) {
			throw new \Exception((string)$this->l10n->t('The name can only contain "a-z", "A-Z", "0-9" and "-_" chars.'));
		}
		if (empty($data['file'])) {
			throw new \Exception((string)$this->l10n->t('Empty file'));
		}
		if (empty($data['file']['url']) && empty($data['file']['base64'])) {
			throw new \Exception((string)$this->l10n->t('Inform url or base64 to sign'));
		}
		if (!empty($data['file']['url'])) {
			if (!filter_var($data['file']['url'], FILTER_VALIDATE_URL)) {
				throw new \Exception((string)$this->l10n->t('Invalid url file'));
			}
		}
		if (!empty($data['file']['base64'])) {
			$input = base64_decode($data['file']['base64']);
			$base64 = base64_encode($input);
			if ($input != $base64) {
				throw new \Exception((string)$this->l10n->t('Invalid base64 file'));
			}
		}
	}

	private function validateUsers($data) {
		if (empty($data['users'])) {
			throw new \Exception((string)$this->l10n->t('Empty users collection'));
		}
		if (!is_array($data['users'])) {
			throw new \Exception((string)$this->l10n->t('User collection need is an array'));
		}
		foreach ($data['users'] as $index => $user) {
			$this->validateUser($user, $index);
		}
	}

	private function validateUser($user, $index) {
		if (!is_array($user)) {
			throw new \Exception((string)$this->l10n->t('User collection need is an array: user ' . $index));
		}
		if (!$user) {
			throw new \Exception((string)$this->l10n->t('User collection need is an array with values: user ' . $index));
		}
		if (empty($user['email'])) {
			throw new \Exception((string)$this->l10n->t('User need an email: user ' . $index));
		}
		if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
			throw new \Exception((string)$this->l10n->t('Invalid email: user ' . $index));
		}
	}

	public function save(array $data) {
		// $userFolder = $this->rootFolder->getUserFolder($this->userId);

		$userFolder = $this->folderService->getFolderForUser();
		$folderName = $this->getFolderName($data);
		if ($userFolder->nodeExists($folderName)) {
			throw new \Exception('Another file like this already exists');
		}
		$folderToFile = $userFolder->newFolder($folderName);
		$folderToFile->newFile($data['name'], $this->getFileRaw($data));
		// $folderToFile->newFile

		// if ($files === []) {
		// 	throw new OCSNotFoundException();
		// }
		// $file = new FileEntity();
		// $file->setFileId($data);
		// $file = $this->mapper->insert($file);
	}

	private function getFileRaw($data) {
		if (!empty($data['file']['url'])) {
			$file = file_get_contents($data['file']['url']);
		}
		return base64_decode($data['file']['base64']);
	}

	private function getFolderName(array $data) {
		$folderName[] = date('Y-m-d\TH:i:s');
		if (!empty($data['name'])) {
			$folderName[] = $data['name'];
		}
		$folderName[] = $data['userManager']->getUID();
		return implode('_', $folderName);
	}
}
