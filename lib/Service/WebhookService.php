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
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Sabre\DAV\UUIDUtil;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\PdfParserException;

class WebhookService {
	/** @var FileEntity */
	private $file;
	/** @var FileUserEntity[] */
	private $signatures;
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
	/** @var MailService */
	private $mail;
	/** @var LoggerInterface */
	private $logger;

	public function __construct(
		IConfig $config,
		IGroupManager $groupManager,
		IL10N $l10n,
		FileMapper $fileMapper,
		FileUserMapper $fileUserMapper,
		FolderService $folderService,
		IClientService $client,
		IUserManager $userManager,
		MailService $mail,
		LoggerInterface $logger
	) {
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->l10n = $l10n;
		$this->fileMapper = $fileMapper;
		$this->fileUserMapper = $fileUserMapper;
		$this->folderService = $folderService;
		$this->client = $client;
		$this->userManager = $userManager;
		$this->mail = $mail;
		$this->logger = $logger;
	}

	public function validate(array $data) {
		$this->validateUserManager($data);
		$this->validateFile($data);
		$this->validateUsers($data);
	}

	public function validateUserManager($user) {
		$authorized = json_decode($this->config->getAppValue(Application::APP_ID, 'webhook_authorized', '["admin"]'));
		if (!empty($authorized)) {
			$userGroups = $this->groupManager->getUserGroupIds($user['userManager']);
			if (!array_intersect($userGroups, $authorized)) {
				throw new \Exception($this->l10n->t('You are not allowed to request signing'), 405);
			}
		}
	}

	public function validateFile(array $data) {
		if (empty($data['name'])) {
			throw new \Exception($this->l10n->t('Name is mandatory'));
		}
		if (!preg_match('/^[\w \-_]+$/', $data['name'])) {
			throw new \Exception($this->l10n->t('The name can only contain "a-z", "A-Z", "0-9" and "-_" chars.'));
		}
		if (empty($data['file'])) {
			throw new \Exception($this->l10n->t('Empty file'));
		}
		if (empty($data['file']['url']) && empty($data['file']['base64']) && empty($data['file']['fileId'])) {
			throw new \Exception($this->l10n->t('Inform URL or base64 or fileId to sign'));
		}
		if (!empty($data['file']['fileId'])) {
			if (!is_numeric($data['file']['fileId'])) {
				throw new \Exception($this->l10n->t('Invalid fileId'));
			}
			$this->validateFileByFileId((int)$data['file']['fileId']);
		}
		if (!empty($data['file']['base64'])) {
			$input = base64_decode($data['file']['base64']);
			$base64 = base64_encode($input);
			if ($data['file']['base64'] !== $base64) {
				throw new \Exception($this->l10n->t('Invalid base64 file'));
			}
		}
	}

	public function validateFileByFileId(int $fileId) {
		try {
			$fileMapper = $this->fileMapper->getByFileId($fileId);
		} catch (\Throwable $th) {
		}
		if ($fileMapper) {
			throw new \Exception($this->l10n->t('Already asked to sign this document'));
		}

		try {
			$userFolder = $this->folderService->getFolder($fileId);
			$node = $userFolder->getById($fileId);
		} catch (\Throwable $th) {
			throw new \Exception($this->l10n->t('Invalid fileId'));
		}
		if (!$node) {
			throw new \Exception($this->l10n->t('Invalid fileId'));
		}
		$node = $node[0];
		if ($node->getMimeType() !== 'application/pdf') {
			throw new \Exception($this->l10n->t('Must be a fileId of a PDF'));
		}
	}

	public function validateFileUuid(array $data) {
		try {
			$this->getFileByUuid($data['uuid']);
		} catch (\Throwable $th) {
			throw new \Exception($this->l10n->t('Invalid UUID file'));
		}
	}

	public function validateUsers(array $data) {
		if (empty($data['users'])) {
			throw new \Exception($this->l10n->t('Empty users list'));
		}
		if (!is_array($data['users'])) {
			throw new \Exception($this->l10n->t('User list needs to be an array'));
		}
		$emails = [];
		foreach ($data['users'] as $index => $user) {
			$this->validateUser($user, $index);
			$emails[$index] = strtolower($user['email']);
		}
		$uniques = array_unique($emails);
		if (count($emails) > count($uniques)) {
			throw new \Exception($this->l10n->t('Remove duplicated users, email address need to be unique'));
		}
	}

	/**
	 * Can delete sing request
	 *
	 * @param array $data
	 */
	public function canDeleteSignRequest(array $data) {
		$signatures = $this->getSignaturesByFileUuid($data['uuid']);
		$signed = array_filter($signatures, fn ($s) => $s->getSigned());
		if ($signed) {
			throw new \Exception($this->l10n->t('Document already signed'));
		}
		array_walk($data['users'], function ($user) use ($signatures) {
			$exists = array_filter($signatures, fn ($s) => $s->getEmail() === $user['email']);
			if (!$exists) {
				throw new \Exception($this->l10n->t('No signature was requested to %s', $user['email']));
			}
		});
	}

	public function deleteSignRequest(array $data) {
		$fileData = $this->getFileByUuid($data['uuid']);
		foreach ($data['users'] as $signer) {
			$fileUser = $this->fileUserMapper->getByEmailAndFileId(
				$signer['email'],
				$fileData->getId()
			);
			$this->fileUserMapper->delete($fileUser);
		}
		$signatures = $this->getSignaturesByFileUuid($data['uuid']);
		if (count($signatures) === count($data['users'])) {
			$file = $this->getFileByUuid($data['uuid']);
			$this->fileMapper->delete($file);
		}
	}

	/**
	 * Get all signatures by file UUID
	 *
	 * @param string $uuid
	 * @return FileUserEntity[]
	 */
	private function getSignaturesByFileUuid(string $uuid): array {
		if (!$this->signatures) {
			$file = $this->getFileByUuid($uuid);
			$this->signatures = $this->fileUserMapper->getByFileId($file->getId());
		}
		return $this->signatures;
	}

	private function validateUser($user, $index) {
		if (!is_array($user)) {
			throw new \Exception($this->l10n->t('User data needs to be an array: user of position %s in list', [$index]));
		}
		if (!$user) {
			throw new \Exception($this->l10n->t('User data needs to be an array with values: user of position %s in list', [$index]));
		}
		if (empty($user['email'])) {
			throw new \Exception($this->l10n->t('User %s needs an email address', [$index]));
		}
		if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
			throw new \Exception($this->l10n->t('Invalid email: user %s', [$index]));
		}
	}

	/**
	 * Get LibreSign file entity by UUID
	 *
	 * @param string $uuid
	 * @return FileEntity
	 */
	private function getFileByUuid(string $uuid): FileEntity {
		if (!$this->file) {
			$this->file = $this->fileMapper->getByUuid($uuid);
		}
		return $this->file;
	}

	public function save(array $data) {
		if (!empty($data['uuid'])) {
			$file = $this->getFileByUuid($data['uuid']);
		} else {
			$file = $this->saveFile($data);
		}
		$return['uuid'] = $file->getUuid();
		$return['users'] = $this->associateToUsers($data, $file->getId());
		return $return;
	}

	private function associateToUsers(array $data, int $fileId) {
		$return = [];
		foreach ($data['users'] as $user) {
			$user['email'] = strtolower($user['email']);
			try {
				$fileUser = $this->fileUserMapper->getByEmailAndFileId($user['email'], $fileId);
			} catch (\Throwable $th) {
				$fileUser = new FileUserEntity();
			}
			$fileUser->setFileId($fileId);
			if (!$fileUser->getUuid()) {
				$fileUser->setUuid(UUIDUtil::getUUID());
			}
			$fileUser->setEmail($user['email']);
			if (!empty($user['description']) && $fileUser->getDescription() !== $user['description']) {
				$fileUser->setDescription($user['description']);
			}
			if (empty($user['user_id'])) {
				$userToSign = $this->userManager->getByEmail($user['email']);
				if ($userToSign) {
					$fileUser->setUserId($userToSign[0]->getUID());
					if (empty($user['display_name'])) {
						$user['display_name'] = $userToSign[0]->getDisplayName();
					}
				}
			}
			if (!empty($user['display_name'])) {
				$fileUser->setDisplayName($user['display_name']);
			}
			if ($fileUser->getId()) {
				$this->fileUserMapper->update($fileUser);
				$this->mail->notifySignDataUpdated($fileUser);
			} else {
				$fileUser->setCreatedAt(time());
				$this->fileUserMapper->insert($fileUser);
				$this->mail->notifyUnsignedUser($fileUser);
			}
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
		$node = $this->getNodeFromData($data);

		$file = new FileEntity();
		$file->setNodeId($node->getId());
		$file->setUserId($data['userManager']->getUID());
		$file->setUuid(UUIDUtil::getUUID());
		$file->setCreatedAt(time());
		$file->setName($data['name']);
		if (!empty($data['callback'])) {
			$file->setCallback($data['callback']);
		}
		$file->setEnabled(1);
		$this->fileMapper->insert($file);
		return $file;
	}

	private function getNodeFromData(array $data) {
		if (isset($data['file']['fileId'])) {
			$userFolder = $this->folderService->getFolder($data['file']['fileId']);
			return $userFolder->getById($data['file']['fileId'])[0];
		}
		$userFolder = $this->folderService->getFolder();
		$folderName = $this->getFolderName($data);
		if ($userFolder->nodeExists($folderName)) {
			throw new \Exception($this->l10n->t('File already exists'));
		}
		$folderToFile = $userFolder->newFolder($folderName);
		return $folderToFile->newFile($data['name'] . '.pdf', $this->getFileRaw($data));
	}

	private function getFileRaw($data) {
		if (!empty($data['file']['url'])) {
			if (!filter_var($data['file']['url'], FILTER_VALIDATE_URL)) {
				throw new \Exception($this->l10n->t('Invalid URL file'));
			}
			$response = $this->client->newClient()->get($data['file']['url']);
			$contentType = $response->getHeaders()['Content-Type'][0];
			if ($contentType !== 'application/pdf') {
				throw new \Exception($this->l10n->t('The URL should be a PDF.'));
			}
			$content = $response->getBody();
			if (!$content) {
				throw new \Exception($this->l10n->t('Empty file'));
			}
		} else {
			$content = base64_decode($data['file']['base64']);
		}
		$this->validatePdfStringWithFpdi($content);
		return $content;
	}

	/**
	 * Validates a PDF. Triggers error if invalid.
	 *
	 * @param string $string
	 *
	 * @throws Type\PdfTypeException
	 * @throws CrossReferenceException
	 * @throws PdfParserException
	 */
	private function validatePdfStringWithFpdi($string) {
		$pdf = new Fpdi();
		try {
			$stream = fopen('php://memory','r+');
			fwrite($stream, $string);
			rewind($stream);
			$pdf->setSourceFile($stream);
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage());
			throw new \Exception($this->l10n->t('Invalid PDF'));
		}
	}

	private function getFolderName(array $data) {
		$folderName[] = date('Y-m-d\TH:i:s');
		if (!empty($data['name'])) {
			$folderName[] = $data['name'];
		}
		$folderName[] = $data['userManager']->getUID();
		return implode('_', $folderName);
	}

	public function notifyCallback(string $uri, string $uuid, File $file): IResponse {
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
		return $this->client->newClient()->post($uri, $options);
	}
}
