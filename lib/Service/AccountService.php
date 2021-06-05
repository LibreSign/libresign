<?php

namespace OCA\Libresign\Service;

use OC\Files\Filesystem;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CfsslHandler;
use OCA\Libresign\Helper\JSActions;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUserManager;
use Sabre\DAV\UUIDUtil;

class AccountService {
	/** @var IL10N */
	private $l10n;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var FileUser */
	private $fileUser;
	/** @var IUserManager */
	protected $userManager;
	/** @var FolderService */
	private $folder;
	/** @var IRootFolder */
	private $root;
	/** @var IConfig */
	private $config;
	/** @var NewUserMailHelper */
	private $newUserMail;
	/** @var CfsslHandler */
	private $cfsslHandler;
	/** @var FileMapper */
	private $fileMapper;
	/** @var string */
	private $pfxFilename = 'signature.pfx';
	/** @var \OCA\Libresign\DbFile */
	private $fileData;
	/** @var \OCA\Files\Node\File */
	private $fileToSign;

	public function __construct(
		IL10N $l10n,
		FileUserMapper $fileUserMapper,
		IUserManager $userManager,
		FolderService $folder,
		IRootFolder $root,
		FileMapper $fileMapper,
		IConfig $config,
		NewUserMailHelper $newUserMail,
		CfsslHandler $cfsslHandler
	) {
		$this->l10n = $l10n;
		$this->fileUserMapper = $fileUserMapper;
		$this->userManager = $userManager;
		$this->folder = $folder;
		$this->root = $root;
		$this->fileMapper = $fileMapper;
		$this->config = $config;
		$this->newUserMail = $newUserMail;
		$this->cfsslHandler = $cfsslHandler;
	}

	public function validateCreateToSign(array $data) {
		if (!UUIDUtil::validateUUID($data['uuid'])) {
			throw new LibresignException($this->l10n->t('Invalid UUID'), 1);
		}
		try {
			$fileUser = $this->getFileUserByUuid($data['uuid']);
		} catch (\Throwable $th) {
			throw new LibresignException($this->l10n->t('UUID not found'), 1);
		}
		if ($fileUser->getEmail() !== $data['email']) {
			throw new LibresignException($this->l10n->t('This is not your file'), 1);
		}
		if ($this->userManager->userExists($data['email'])) {
			throw new LibresignException($this->l10n->t('User already exists'), 1);
		}
		if (empty($data['password'])) {
			throw new LibresignException($this->l10n->t('Password is mandatory'), 1);
		}
		$file = $this->getFileByUuid($data['uuid']);
		if (empty($file['fileToSign'])) {
			throw new LibresignException($this->l10n->t('File not found'));
		}
	}

	public function getFileByUuid(string $uuid) {
		$fileUser = $this->getFileUserByUuid($uuid);
		if (!$this->fileData) {
			$this->fileData = $this->fileMapper->getById($fileUser->getFileId());
			$fileToSign = $this->root->getById($this->fileData->getNodeId());
			if (count($fileToSign)) {
				$this->fileToSign = $fileToSign[0];
			}
		}
		return [
			'fileData' => $this->fileData,
			'fileToSign' => $this->fileToSign
		];
	}

	public function validateCertificateData(array $data) {
		if (!$data['email']) {
			throw new LibresignException($this->l10n->t('You must have an email. You can define the email in your profile.'), 1);
		}
		if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			throw new LibresignException($this->l10n->t('Invalid email'), 1);
		}
		if (empty($data['signPassword'])) {
			throw new LibresignException($this->l10n->t('Password to sign is mandatory'), 1);
		}
	}

	/**
	 * Get fileUser by Uuid
	 *
	 * @param string $uuid
	 * @return FileUser
	 */
	public function getFileUserByUuid($uuid) {
		if (!$this->fileUser) {
			$this->fileUser = $this->fileUserMapper->getByUuid($uuid);
		}
		return $this->fileUser;
	}

	public function createToSign($uuid, $uid, $password, $signPassword) {
		$fileUser = $this->getFileUserByUuid($uuid);

		$newUser = $this->userManager->createUser($uid, $password);
		$newUser->setDisplayName($fileUser->getDisplayName());
		$newUser->setEMailAddress($fileUser->getEmail());

		$fileUser->setUserId($newUser->getUID());
		$this->fileUserMapper->update($fileUser);

		if ($this->config->getAppValue('core', 'newUser.sendEmail', 'yes') === 'yes') {
			try {
				$emailTemplate = $this->newUserMail->generateTemplate($newUser, false);
				$this->newUserMail->sendMail($newUser, $emailTemplate);
			} catch (\Exception $e) {
				throw new LibresignException('Unable to send the invitation', 1);
			}
		}

		$this->generateCertificate($uid, $signPassword, $newUser->getUID());
	}

	/**
	 * Generate certificate
	 *
	 * @param string $email Email
	 * @param string $signPassword Password of signature
	 * @param string $uid User id
	 * @return File
	 */
	public function generateCertificate(string $email, string $signPassword, string $uid): File {
		$content = $this->cfsslHandler
			->setCommonName($this->config->getAppValue(Application::APP_ID, 'commonName'))
			->sethosts([$email])
			->setCountry($this->config->getAppValue(Application::APP_ID, 'country'))
			->setOrganization($this->config->getAppValue(Application::APP_ID, 'organization'))
			->setOrganizationUnit($this->config->getAppValue(Application::APP_ID, 'organizationUnit'))
			->setCfsslUri($this->config->getAppValue(Application::APP_ID, 'cfsslUri'))
			->setPassword($signPassword)
			->generateCertificate();
		if (!$content) {
			throw new LibresignException('Failure on generate certificate', 1);
		}
		return $this->savePfx($uid, $content);
	}

	private function savePfx($uid, $content): File {
		$this->folder->setUserId($uid);
		Filesystem::initMountPoints($uid);
		$folder = $this->folder->getFolder();
		if ($folder->nodeExists($this->pfxFilename)) {
			$file = $folder->get($this->pfxFilename);
			if (!$file instanceof File) {
				throw new LibresignException("path {$this->pfxFilename} already exists and is not a file!", 400);
			}
			$file->putContent($content);
			return $file;
		}

		$file = $folder->newFile($this->pfxFilename);
		$file->putContent($content);
		return $file;
	}

	/**
	 * Get pfx file
	 *
	 * @param string $uid user id
	 * @return \OCP\Files\Node
	 */
	public function getPfx($uid) {
		Filesystem::initMountPoints($uid);
		$this->folder->setUserId($uid);
		$folder = $this->folder->getFolder();
		if (!$folder->nodeExists($this->pfxFilename)) {
			throw new \Exception('Password to sign not defined. Create a password to sign', 400);
		}
		return $folder->get($this->pfxFilename);
	}

	/**
	 * Undocumented function
	 *
	 * @param string $formatOfPdfOnSign (base64,url,file)
	 * @return array|string
	 */
	public function getConfig(string $formatOfPdfOnSign): array {
		$info = $this->getInfoOfFileToSign($formatOfPdfOnSign);
		$info['settings'] = [
			'hasSignatureFile' => $this->hasSignatureFile()
		];
		return $info;
	}

	private function getInfoOfFileToSign(string $formatOfPdfOnSign): array {
		$uuid = $this->request->getParam('uuid');
		$userId = $this->session->get('user_id');
		$return = [];
		try {
			if (!$uuid) {
				return $return;
			}
			$fileUser = $this->fileUserMapper->getByUuid($uuid);
		} catch (\Throwable $th) {
			$return['action'] = JSActions::ACTION_DO_NOTHING;
			$return['errors'][] = $this->l10n->t('Invalid UUID');
			return $return;
		}
		$fileUserId = $fileUser->getUserId();
		if (!$fileUserId) {
			if ($userId) {
				$return['action'] = JSActions::ACTION_DO_NOTHING;
				$return['errors'][] = $this->l10n->t('This is not your file');
				return $return;
			}
			if ($this->userManager->userExists($fileUser->getEmail())) {
				$return['action'] = JSActions::ACTION_REDIRECT;
				$return['errors'][] = $this->l10n->t('User already exists. Please login.');
				$return['redirect'] = $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
					'redirect_url' => $this->urlGenerator->linkToRoute(
						'libresign.page.sign',
						['uuid' => $uuid]
					),
				]);
				return $return;
			}
			$return['action'] = JSActions::ACTION_CREATE_USER;
			return $return;
		}
		if ($fileUser->getSigned()) {
			$return['action'] = JSActions::ACTION_SHOW_ERROR;
			$return['errors'][] = $this->l10n->t('File already signed.');
			return $return;
		}
		if (!$userId) {
			$return['action'] = JSActions::ACTION_REDIRECT;

			$return['redirect'] = $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
				'redirect_url' => $this->urlGenerator->linkToRoute(
					'libresign.page.sign',
					['uuid' => $uuid]
				),
			]);
			$return['errors'][] = $this->l10n->t('You are not logged in. Please log in.');
			return $return;
		}
		if ($fileUserId !== $userId) {
			$return['action'] = JSActions::ACTION_DO_NOTHING;
			$return['errors'][] = $this->l10n->t('Invalid user');
			return $return;
		}
		$fileData = $this->fileMapper->getById($fileUser->getFileId());
		Filesystem::initMountPoints($fileData->getUserId());
		$fileToSign = $this->root->getById($fileData->getNodeId());
		if (count($fileToSign) < 1) {
			$return['action'] = JSActions::ACTION_DO_NOTHING;
			$return['errors'][] = $this->l10n->t('File not found');
			return $return;
		}
		/** @var File */
		$fileToSign = $fileToSign[0];
		$return['action'] = JSActions::ACTION_SIGN;
		$return['user']['name'] = $fileUser->getDisplayName();
		switch ($formatOfPdfOnSign) {
			case 'base64':
				$pdf = ['base64' => base64_encode($fileToSign->getContent())];
				break;
			case 'url':
				$pdf = ['url' => $this->urlGenerator->linkToRoute('libresign.page.getPdfUser', ['uuid' => $uuid])];
				break;
			case 'file':
				$pdf = ['file' => $fileToSign];
				break;
		}
		$return['sign'] = [
			'pdf' => $pdf,
			'filename' => $fileData->getName(),
			'description' => $fileUser->getDescription()
		];
		return $return;
	}

	private function hasSignatureFile() {
		$userId = $this->session->get('user_id');
		if (!$userId) {
			return false;
		}
		try {
			$this->accountService->getPfx($userId);
			return true;
		} catch (\Throwable $th) {
		}
		return false;
	}
}
