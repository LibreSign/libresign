<?php

namespace OCA\Libresign\Helper;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\LibreSign\Db\File as LibresignFile;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUser;
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
	/** @var AccountFileMapper */
	private $accountFileMapper;
	/** @var IConfig */
	private $config;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IRootFolder */
	private $root;
	/** @var LibresignFile */
	private $libresignFile;
	/** @var FileUser[] */
	private $signers;

	public function __construct(
		IL10N $l10n,
		FileUserMapper $fileUserMapper,
		FileMapper $fileMapper,
		AccountFileMapper $accountFileMapper,
		IConfig $config,
		IGroupManager $groupManager,
		IRootFolder $root
	) {
		$this->l10n = $l10n;
		$this->fileUserMapper = $fileUserMapper;
		$this->fileMapper = $fileMapper;
		$this->accountFileMapper = $accountFileMapper;
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->root = $root;
	}
	public function validateNewFile(array $data) {
		$this->validateFile($data, 'to_sign');
		if (!empty($data['file']['fileId'])) {
			$this->validateNotRequestedSign((int)$data['file']['fileId']);
		}
	}

	/**
	 * @property array $data
	 * @property string $destination to_sign|visible_element
	 */
	public function validateFile(array $data, string $destination = 'to_sign') {
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
			$this->validateIfNodeIdExists((int)$data['file']['fileId']);
			$this->validateMimeTypeAccepted((int)$data['file']['fileId'], $destination);
		}
		if (!empty($data['file']['base64'])) {
			$this->validateBase64($data['file']['base64']);
		}
	}

	public function validateBase64(string $base64) {
		$string = base64_decode($base64);
		$newBase64 = base64_encode($string);
		if ($newBase64 !== $base64) {
			throw new \Exception($this->l10n->t('Invalid base64 file'));
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

	public function validateVisibleElements($visibleElements) {
		if (!is_array($visibleElements)) {
			throw new \Exception($this->l10n->t('Visible elements need to be an array'));
		}
		foreach ($visibleElements as $element) {
			$this->validateVisibleElement($element);
		}
	}

	public function validateVisibleElement(array $element): void {
		$this->validateElementType($element);
		$this->validateFile($element, 'visible_element');
		$this->validateElementCoordinates($element);
	}

	public function validateElementCoordinates(array $element): void {
		if (!array_key_exists('coordinates', $element)) {
			return;
		}
		$this->validateElementPage($element);
	}

	protected function acceptedCoordinates() {
	}

	public function validateElementPage(array $element): void {
		if (!array_key_exists('page', $element['coordinates'])) {
			return;
		}
		if (!is_int($element['coordinates']['page'])) {
			throw new \Exception($this->l10n->t('Page number must be an integer'));
		}
		if ($element['coordinates']['page'] < 1) {
			throw new \Exception($this->l10n->t('Page must be equal to or greater than 1'));
		}
	}

	public function validateElementType(array $element) {
		if (!array_key_exists('type', $element)) {
			throw new \Exception($this->l10n->t('Element needs a type'));
		}
		if (!in_array($element['type'], ['signature', 'initial', 'date', 'datetime', 'text'])) {
			throw new \Exception($this->l10n->t('Invalid element type'));
		}
	}

	public function validateIfNodeIdExists(int $nodeId) {
		try {
			$file = $this->root->getById($nodeId);
			$file = $file[0];
		} catch (\Throwable $th) {
			throw new \Exception($this->l10n->t('Invalid fileID'));
		}
		if (!$file) {
			throw new \Exception($this->l10n->t('Invalid fileID'));
		}
	}

	public function validateMimeTypeAccepted(int $nodeId, string $destination = 'to_sign') {
		$file = $this->root->getById($nodeId);
		$file = $file[0];
		if ($destination === 'to_sign') {
			if ($file->getMimeType() !== 'application/pdf') {
				throw new \Exception($this->l10n->t('Must be a fileID of %s format', 'PDF'));
			}
		} elseif ($destination === 'visible_element') {
			if ($file->getMimeType() !== 'image/png') {
				throw new \Exception($this->l10n->t('Must be a fileID of %s format', 'png'));
			}
		}
	}

	public function validateLibreSignNodeId(int $nodeId) {
		try {
			$this->getLibreSignFileByNodeId($nodeId);
		} catch (\Throwable $th) {
			throw new \Exception($this->l10n->t('Invalid fileID'));
		}
	}

	private function getLibreSignFileByNodeId(int $nodeId): \OCP\Files\File {
		if (empty($this->file[$nodeId])) {
			$libresignFile = $this->fileMapper->getByFileId($nodeId);

			$userFolder = $this->root->getUserFolder($libresignFile->getUserId());
			$this->file[$nodeId] = $userFolder->getById($nodeId);
			if (!empty($this->file[$nodeId])) {
				$this->file[$nodeId] = $this->file[$nodeId][0];
			}
		}
		return $this->file[$nodeId];
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
		$libresignFile = $this->fileMapper->getByFileId($nodeId);
		if ($libresignFile->getUserId() !== $user->getUID()) {
			throw new \Exception($this->l10n->t('You do not have permission for this action.'));
		}
	}

	public function haveValidMail(array $data) {
		if (empty($data)) {
			throw new \Exception($this->l10n->t('No user data'));
		}
		if (empty($data['email'])) {
			throw new \Exception($this->l10n->t('Email required'));
		}
		if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			throw new \Exception($this->l10n->t('Invalid email'));
		}
	}

	public function signerWasAssociated(array $signer) {
		$libresignFile = $this->fileMapper->getByFileId();
		if (!$libresignFile) {
			throw new \Exception($this->l10n->t('File not loaded'));
		}
		$signatures = $this->fileUserMapper->getByFileUuid($libresignFile->getUuid());
		$exists = array_filter($signatures, fn ($s) => $s->getEmail() === $signer['email']);
		if (!$exists) {
			throw new \Exception($this->l10n->t('No signature was requested to %s', $signer['email']));
		}
	}

	public function notSigned(array $signer) {
		$libresignFile = $this->fileMapper->getByFileId();
		if (!$libresignFile) {
			throw new \Exception($this->l10n->t('File not loaded'));
		}
		$signatures = $this->fileUserMapper->getByFileUuid($libresignFile->getUuid());
		$exists = array_filter($signatures, fn ($s) => $s->getEmail() === $signer['email'] && $s->getSigned());
		if (!$exists) {
			return;
		}
		$firstSigner = array_values($exists)[0];
		if ($firstSigner->getDisplayName()) {
			throw new \Exception($this->l10n->t('%s already signed this file', $firstSigner->getDisplayName()));
		}
		throw new \Exception($this->l10n->t('%s already signed this file', $firstSigner->getDisplayName()));
	}

	public function validateFileUuid(array $data) {
		try {
			$this->fileMapper->getByUuid($data['uuid']);
		} catch (\Throwable $th) {
			throw new \Exception($this->l10n->t('Invalid UUID file'));
		}
	}

	public function validateIsSignerOfFile(int $signatureId, int $fileId) {
		try {
			$this->fileUserMapper->getByFileIdAndFileUserId($fileId, $signatureId);
		} catch (\Throwable $th) {
			throw new \Exception($this->l10n->t('Signer not associated to this file'));
		}
	}

	public function validateUserHasNoFileWithThisType(string $uid, string $type) {
		try {
			$exists = $this->accountFileMapper->getByUserAndType($uid, $type);
		} catch (\Throwable $th) {
		}
		if (!empty($exists)) {
			throw new \Exception($this->l10n->t('A file of this type has been associated.'));
		}
	}

	public function validateFileTypeExists(string $type) {
		$profileFileTypes = json_decode($this->config->getAppValue(Application::APP_ID, 'profile_file_types', '["IDENTIFICATION"]'), true);
		if (!in_array($type, $profileFileTypes)) {
			throw new \Exception($this->l10n->t('Invalid file type.'));
		}
	}
}
