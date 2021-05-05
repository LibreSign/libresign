<?php

namespace OCA\Libresign\Service;

use OC\Files\Filesystem;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CfsslHandler;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\Files\File;
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
	/** @var IConfig */
	private $config;
	/** @var NewUserMailHelper */
	private $newUserMail;
	/** @var CfsslHandler */
	private $cfsslHandler;
	/** @var string */
	private $pdfFilename = 'signature.pfx';

	public function __construct(
		IL10N $l10n,
		FileUserMapper $fileUserMapper,
		IUserManager $userManager,
		FolderService $folder,
		IConfig $config,
		NewUserMailHelper $newUserMail,
		CfsslHandler $cfsslHandler
	) {
		$this->l10n = $l10n;
		$this->fileUserMapper = $fileUserMapper;
		$this->userManager = $userManager;
		$this->folder = $folder;
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
		if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			throw new LibresignException($this->l10n->t('Invalid email'), 1);
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

		$content = $this->cfsslHandler->generateCertificate(
			$this->config->getAppValue(Application::APP_ID, 'commonName'),
			[],
			$this->config->getAppValue(Application::APP_ID, 'country'),
			$this->config->getAppValue(Application::APP_ID, 'organization'),
			$this->config->getAppValue(Application::APP_ID, 'organizationUnit'),
			$signPassword,
			$this->config->getAppValue(Application::APP_ID, 'cfsslUri')
		);
		if (!$content) {
			throw new LibresignException('Failure on generate certificate', 1);
		}
		$this->savePfx($uid, $content);
	}

	private function savePfx($uid, $content) {
		$this->folder->setUserId($uid);
		Filesystem::initMountPoints($uid);
		$folder = $this->folder->getFolder();
		if ($folder->nodeExists($this->pdfFilename)) {
			$node = $folder->get($this->pdfFilename);
			if (!$node instanceof File) {
				throw new LibresignException("path {$this->pdfFilename} already exists and is not a file!", 400);
			}
			$node->putContent($content);
			return $node;
		}

		$file = $folder->newFile($this->pdfFilename);
		$file->putContent($content);
	}

	public function getPfx($uid) {
		Filesystem::initMountPoints($uid);
		$this->folder->setUserId($uid);
		$folder = $this->folder->getFolder();
		if (!$folder->nodeExists($this->pdfFilename)) {
			throw new LibresignException('Signature file not found!', 400);
		}
		return $folder->get($this->pdfFilename);
	}
}
