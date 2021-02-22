<?php

namespace OCA\Libresign\Service;

use OC\Http\Client\ClientService;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUser as FileUserEntity;
use OCA\Libresign\Db\FileUserMapper;
use OCP\Files\File;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use Sabre\DAV\UUIDUtil;

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
	/** @var FolderService */
	private $folderService;
	/** @var ClientService */
	private $client;
	/** @var IUserManager */
	private $userManager;

	public function __construct(
		IConfig $config,
		IGroupManager $groupManager,
		IL10N $l10n,
		FileMapper $fileMapper,
		FileUserMapper $fileUserMapper,
		FolderService $folderService,
		IClientService $client,
		IUserManager $userManager
	) {
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->l10n = $l10n;
		$this->fileMapper = $fileMapper;
		$this->fileUserMapper = $fileUserMapper;
		$this->folderService = $folderService;
		$this->client = $client;
		$this->userManager = $userManager;
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
			throw new \Exception($this->l10n->t('Name is mandatory'));
		}
		if (!preg_match('/^[\w \-_]+$/', $data['name'])) {
			throw new \Exception($this->l10n->t('The name can only contain "a-z", "A-Z", "0-9" and "-_" chars.'));
		}
		if (empty($data['file'])) {
			throw new \Exception($this->l10n->t('Empty file'));
		}
		if (empty($data['file']['url']) && empty($data['file']['base64'])) {
			throw new \Exception($this->l10n->t('Inform url or base64 to sign'));
		}
		if (!empty($data['file']['url'])) {
			if (!filter_var($data['file']['url'], FILTER_VALIDATE_URL)) {
				throw new \Exception($this->l10n->t('Invalid url file'));
			}
			$response = $this->client->newClient()->get($data['file']['url']);
			$contentType = $response->getHeaders()['Content-Type'][0];
			if ($contentType != 'application/pdf') {
				throw new \Exception($this->l10n->t('The URL should be a PDF.'));
			}
		}
		if (!empty($data['file']['base64'])) {
			$input = base64_decode($data['file']['base64']);
			$base64 = base64_encode($input);
			if ($data['file']['base64'] != $base64) {
				throw new \Exception($this->l10n->t('Invalid base64 file'));
			}
		}
	}

	private function validateUsers($data) {
		if (empty($data['users'])) {
			throw new \Exception($this->l10n->t('Empty users collection'));
		}
		if (!is_array($data['users'])) {
			throw new \Exception($this->l10n->t('User collection need to be an array'));
		}
		$emails = [];
		foreach ($data['users'] as $index => $user) {
			$this->validateUser($user, $index);
			$emails[$index] = $user['email'];
		}
		$uniques = array_unique($emails);
		if (count($emails) > count($uniques)) {
			throw new \Exception($this->l10n->t('Remove duplicated users, email need to be unique'));
		}
	}

	private function validateUser($user, $index) {
		if (!is_array($user)) {
			throw new \Exception($this->l10n->t('User collection need to be an array: user ' . $index));
		}
		if (!$user) {
			throw new \Exception($this->l10n->t('User collection need to be an array with values: user ' . $index));
		}
		if (empty($user['email'])) {
			throw new \Exception($this->l10n->t('User need to be email: user ' . $index));
		}
		if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
			throw new \Exception($this->l10n->t('Invalid email: user ' . $index));
		}
	}

	public function save(array $data) {
		$file = $this->saveFile($data);
		$return['uuid'] = $file->getUuid();
		$return['users'] = $this->associateToUsers($data, $file->getId());
		return $return;
	}

	public function associateToUsers(array $data, int $fileId) {
		$return = [];
		foreach ($data['users'] as $user) {
			$fileUser = new FileUserEntity();
			$fileUser->setFileId($fileId);
			$fileUser->setUuid(UUIDUtil::getUUID());
			$fileUser->setCreatedAt(time());
			$fileUser->setEmail($user['email']);
			$fileUser->setDisplayName($user['display_name']);
			if (!$user['user_id']) {
				$userToSign = $this->userManager->getByEmail($user['email']);
				if ($userToSign) {
					$fileUser->setUserId($userToSign[0]->getUID());
				}
			}
			$this->fileUserMapper->insert($fileUser);
			$return[] = $fileUser;
		}
		return $return;
	}

	/**
	 * Save file data
	 *
	 * @param array $data
	 * @return FileEntity
	 */
	public function saveFile(array $data): FileEntity {
		$userFolder = $this->folderService->getFolderForUser();
		$folderName = $this->getFolderName($data);
		if ($userFolder->nodeExists($folderName)) {
			throw new \Exception('Another file like this already exists');
		}
		$folderToFile = $userFolder->newFolder($folderName);
		$node = $folderToFile->newFile($data['name'] . '.pdf', $this->getFileRaw($data));

		$file = new FileEntity();
		$file->setNodeId($node->getId());
		$file->setUserId($data['userManager']->getUID());
		$file->setUuid(UUIDUtil::getUUID());
		$file->setCreatedAt(time());
		if (!empty($data['description'])) {
			$file->setDescription($data['description']);
		}
		$file->setName($data['name']);
		if (!empty($data['callback'])) {
			$file->setCallback($data['callback']);
		}
		$file->setEnabled(1);
		$this->fileMapper->insert($file);
		return $file;
	}

	private function getFileRaw($data) {
		if (!empty($data['file']['url'])) {
			$response = $this->client->newClient()->get($data['file']['url']);
			return $response->getBody();
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

	public function notifyCallback(string $uri, string $uuid, File $file) {
		$options = [
			'multipart' => [
				[
					'name' => 'uuid',
					'contents' => $uuid
				],
				[
					'name' => 'file',
					'contents' => $file->fopen('r'),
					'filename' => $file->getName()
				]
			]
		];
		$response = $this->client->newClient()->post($uri, $options);
	}
}
