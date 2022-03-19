<?php

namespace OCA\Libresign\Helper;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\IHasher;

class ValidateHelper {
	/** @var IL10N */
	private $l10n;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var FileMapper */
	private $fileMapper;
	/** @var FileTypeMapper */
	private $fileTypeMapper;
	/** @var FileElementMapper */
	private $fileElementMapper;
	/** @var AccountFileMapper */
	private $accountFileMapper;
	/** @var UserElementMapper */
	private $userElementMapper;
	/** @var IHasher */
	private $hasher;
	/** @var IConfig */
	private $config;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IUserManager */
	private $userManager;
	/** @var IRootFolder */
	private $root;
	/** @var \OCP\Files\File[] */
	private $file;

	public const TYPE_TO_SIGN = 1;
	public const TYPE_VISIBLE_ELEMENT_PDF = 2;
	public const TYPE_VISIBLE_ELEMENT_USER = 3;

	public const STATUS_DRAFT = 0;
	public const STATUS_ABLE_TO_SIGN = 1;
	public const STATUS_PARTIAL_SIGNED = 2;
	public const STATUS_SIGNED = 3;
	public const STATUS_DELETED = 4;

	public function __construct(
		IL10N $l10n,
		FileUserMapper $fileUserMapper,
		FileMapper $fileMapper,
		FileTypeMapper $fileTypeMapper,
		FileElementMapper $fileElementMapper,
		AccountFileMapper $accountFileMapper,
		UserElementMapper $userElementMapper,
		IHasher $hasher,
		IConfig $config,
		IGroupManager $groupManager,
		IUserManager $userManager,
		IRootFolder $root
	) {
		$this->l10n = $l10n;
		$this->fileUserMapper = $fileUserMapper;
		$this->fileMapper = $fileMapper;
		$this->fileTypeMapper = $fileTypeMapper;
		$this->fileElementMapper = $fileElementMapper;
		$this->accountFileMapper = $accountFileMapper;
		$this->userElementMapper = $userElementMapper;
		$this->hasher = $hasher;
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->root = $root;
	}
	public function validateNewFile(array $data): void {
		$this->validateFile($data, self::TYPE_TO_SIGN);
		if (!empty($data['file']['fileId'])) {
			$this->validateNotRequestedSign((int)$data['file']['fileId']);
		}
	}

	/**
	 * @property array $data
	 * @property int $type to_sign|visible_element
	 *
	 * @return void
	 */
	public function validateFile(array $data, int $type = self::TYPE_TO_SIGN): void {
		if (empty($data['file'])) {
			if ($type === self::TYPE_TO_SIGN) {
				throw new LibresignException($this->l10n->t('File type: %s. Empty file.', [$this->getTypeOfFile($type)]));
			}
			if ($type === self::TYPE_VISIBLE_ELEMENT_USER) {
				if ($this->elementNeedFile($data)) {
					throw new LibresignException($this->l10n->t('Elements of type %s need file.', [$data['type']]));
				}
			}
			return;
		}
		if (empty($data['file']['url']) && empty($data['file']['base64']) && empty($data['file']['fileId'])) {
			throw new LibresignException($this->l10n->t('File type: %s. Inform URL or base64 or fileID.', [$this->getTypeOfFile($type)]));
		}
		if (!empty($data['file']['fileId'])) {
			if (!is_numeric($data['file']['fileId'])) {
				throw new LibresignException($this->l10n->t('File type: %s. Invalid fileID.', [$this->getTypeOfFile($type)]));
			}
			$this->validateIfNodeIdExists((int)$data['file']['fileId'], $type);
			$this->validateMimeTypeAcceptedByNodeId((int)$data['file']['fileId'], $type);
		}
		if (!empty($data['file']['base64'])) {
			$this->validateBase64($data['file']['base64'], $type);
		}
	}

	private function elementNeedFile(array $data): bool {
		return in_array($data['type'], ['signature', 'initial']);
	}

	private function getTypeOfFile(int $type): string {
		if ($type === self::TYPE_TO_SIGN) {
			return $this->l10n->t('document to sign');
		}
		return $this->l10n->t('visible element');
	}

	public function validateBase64(string $base64, int $type = self::TYPE_TO_SIGN): void {
		$withMime = explode(',', $base64);
		if (count($withMime) === 2) {
			$withMime[0] = explode(';', $withMime[0]);
			if (count($withMime[0]) !== 2) {
				throw new LibresignException($this->l10n->t('File type: %s. Invalid base64 file.', [$this->getTypeOfFile($type)]));
			}
			if ($withMime[0][0] !== 'data:application/pdf' || $withMime[0][1] !== 'base64') {
				throw new LibresignException($this->l10n->t('File type: %s. Invalid base64 file.', [$this->getTypeOfFile($type)]));
			}
			$base64 = $withMime[1];
		}
		$string = base64_decode($base64);
		$newBase64 = base64_encode($string);
		if ($newBase64 !== $base64) {
			throw new LibresignException($this->l10n->t('File type: %s. Invalid base64 file.', [$this->getTypeOfFile($type)]));
		}
	}

	public function validateNotRequestedSign(int $nodeId): void {
		try {
			$fileMapper = $this->fileUserMapper->getByNodeId($nodeId);
		} catch (\Throwable $th) {
		}
		if (!empty($fileMapper)) {
			throw new LibresignException($this->l10n->t('Already asked to sign this document'));
		}
	}

	public function validateVisibleElements(?array $visibleElements, int $type): void {
		if (!is_array($visibleElements)) {
			throw new LibresignException($this->l10n->t('Visible elements need to be an array'));
		}
		foreach ($visibleElements as $element) {
			$this->validateVisibleElement($element, $type);
		}
	}

	public function validateVisibleElement(array $element, int $type): void {
		$this->validateElementType($element);
		$this->validateElementFileUserId($element, $type);
		$this->validateFile($element, $type);
		$this->validateElementCoordinates($element);
	}

	public function validateElementFileUserId(array $element, int $type): void {
		if ($type !== self::TYPE_VISIBLE_ELEMENT_PDF) {
			return;
		}
		if (!array_key_exists('fileUserId', $element)) {
			throw new LibresignException($this->l10n->t('Element must be associated with a user'));
		}
		try {
			$this->fileUserMapper->getById($element['fileUserId']);
		} catch (\Throwable $th) {
			throw new LibresignException($this->l10n->t('User not found for element.'));
		}
	}

	public function validateElementCoordinates(array $element): void {
		if (!array_key_exists('coordinates', $element)) {
			return;
		}
		$this->validateElementPage($element);
		$this->validateElementCoordinate($element);
	}

	/**
	 * @psalm-param array{coordinates: mixed} $element
	 */
	private function validateElementCoordinate(array $element): void {
		foreach ($element['coordinates'] as $type => $value) {
			if (in_array($type, ['llx', 'lly', 'urx', 'ury', 'width', 'height', 'left', 'top'])) {
				if (!is_int($value)) {
					throw new LibresignException($this->l10n->t('Coordinate %s must be an integer', [$type]));
				}
				if ($value < 0) {
					throw new LibresignException($this->l10n->t('Coordinate %s must be equal to or greater than 0', [$type]));
				}
			}
		}
	}

	public function validateElementPage(array $element): void {
		if (!array_key_exists('page', $element['coordinates'])) {
			return;
		}
		if (!is_int($element['coordinates']['page'])) {
			throw new LibresignException($this->l10n->t('Page number must be an integer'));
		}
		if ($element['coordinates']['page'] < 1) {
			throw new LibresignException($this->l10n->t('Page must be equal to or greater than 1'));
		}
	}

	public function validateElementType(array $element): void {
		if (!array_key_exists('type', $element)) {
			if (!array_key_exists('elementId', $element)) {
				throw new LibresignException($this->l10n->t('Element needs a type'));
			}
			return;
		}
		if (!in_array($element['type'], ['signature', 'initial', 'date', 'datetime', 'text'])) {
			throw new LibresignException($this->l10n->t('Invalid element type'));
		}
	}

	public function validateVisibleElementsRelation(array $list, FileUser $fileUser): void {
		foreach ($list as $elements) {
			if (!array_key_exists('documentElementId', $elements)) {
				throw new LibresignException($this->l10n->t('Field %s not found', ['documentElementId']));
			}
			if (!array_key_exists('profileElementId', $elements)) {
				throw new LibresignException($this->l10n->t('Field %s not found', ['profileElementId']));
			}
			$this->validateUserIsOwnerOfPdfVisibleElement($elements['documentElementId'], $fileUser->getUserId());
			try {
				$this->userElementMapper->findOne(['id' => $elements['profileElementId'], 'user_id' => $fileUser->getUserId()]);
			} catch (\Throwable $th) {
				throw new LibresignException($this->l10n->t('Field %s does not belong to user', $elements['profileElementId']));
			}
		}
		$this->validateUserHasNecessaryElements($fileUser, $list);
	}

	private function validateUserHasNecessaryElements(FileUser $fileUser, array $list = []): void {
		$fileElements = $this->fileElementMapper->getByFileIdAndUserId($fileUser->getFileId(), $fileUser->getUserId());
		$total = array_filter($fileElements, function (FileElement $fileElement) use ($list, $fileUser): bool {
			$found = array_filter($list, function ($item) use ($fileElement): bool {
				return $item['documentElementId'] === $fileElement->getId();
			});
			if (!$found) {
				try {
					$this->userElementMapper->findMany([
						'user_id' => $fileUser->getUserId(),
						'type' => $fileElement->getType(),
					]);
					return true;
				} catch (\Throwable $th) {
					throw new LibresignException($this->l10n->t('You need to define a visible signature or initials to sign this document.'));
				}
			}
			return true;
		});
		if (count($total) !== count($fileElements)) {
			throw new LibresignException($this->l10n->t('You need to define a visible signature or initials to sign this document.'));
		}
	}

	public function validateUserIsOwnerOfPdfVisibleElement(int $documentElementId, string $uid): void {
		try {
			$this->fileElementMapper->getByDocumentElementIdAndFileUserId($documentElementId, $uid);
		} catch (\Throwable $th) {
			throw new LibresignException($this->l10n->t('Field %s does not belong to user', $documentElementId));
		}
	}

	public function validateAccountFileIsOwnedByUser(int $nodeId, string $uid): void {
		try {
			$this->accountFileMapper->getByUserIdAndNodeId($uid, $nodeId);
		} catch (\Throwable $th) {
			throw new LibresignException($this->l10n->t('This file is not yours'));
		}
	}

	public function fileCanBeSigned(File $file): void {
		$statusList = [
			File::STATUS_ABLE_TO_SIGN,
			File::STATUS_PARTIAL_SIGNED
		];
		if (!in_array($file->getStatus(), $statusList)) {
			$statusText = $this->fileMapper->getTextOfStatus($file->getStatus());
			throw new LibresignException($this->l10n->t('This file cannot be signed. Invalid status: %s', $statusText));
		}
	}

	public function validateIfNodeIdExists(int $nodeId, int $type = self::TYPE_TO_SIGN): void {
		try {
			$file = $this->root->getById($nodeId);
			$file = $file[0] ?? null;
		} catch (\Throwable $th) {
			throw new LibresignException($this->l10n->t('File type: %s. Invalid fileID.', [$this->getTypeOfFile($type)]));
		}
		if (!$file) {
			throw new LibresignException($this->l10n->t('File type: %s. Invalid fileID.', [$this->getTypeOfFile($type)]));
		}
	}

	public function validateMimeTypeAcceptedByNodeId(int $nodeId, int $type = self::TYPE_TO_SIGN): void {
		$file = $this->root->getById($nodeId);
		$file = $file[0];
		$this->validateMimeTypeAcceptedByMime($file->getMimeType(), $type);
	}

	public function validateMimeTypeAcceptedByMime(string $mimetype, int $type = self::TYPE_TO_SIGN): void {
		switch ($type) {
			case self::TYPE_TO_SIGN:
				if ($mimetype !== 'application/pdf') {
					throw new LibresignException($this->l10n->t('File type: %s. Must be a fileID of %s format.', [$this->getTypeOfFile($type), 'PDF']));
				}
				break;
			case self::TYPE_VISIBLE_ELEMENT_PDF:
			case self::TYPE_VISIBLE_ELEMENT_USER:
				if ($mimetype !== 'image/png') {
					throw new LibresignException($this->l10n->t('File type: %s. Must be a fileID of %s format.', [$this->getTypeOfFile($type), 'png']));
				}
				break;
		}
	}

	public function validateLibreSignNodeId(int $nodeId): void {
		try {
			$this->getLibreSignFileByNodeId($nodeId);
		} catch (\Throwable $th) {
			throw new LibresignException($this->l10n->t('Invalid fileID'));
		}
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 *
	 * @param integer $nodeId
	 *
	 * @return \OCP\Files\Node|array
	 *
	 * @psalm-return \OCP\Files\Node|array<empty, empty>
	 */
	private function getLibreSignFileByNodeId(int $nodeId) {
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

	public function canRequestSign(IUser $user): void {
		$authorized = json_decode($this->config->getAppValue(Application::APP_ID, 'webhook_authorized', '["admin"]'));
		if (empty($authorized) || !is_array($authorized)) {
			throw new LibresignException($this->l10n->t('You are not allowed to request signing'));
		}
		$userGroups = $this->groupManager->getUserGroupIds($user);
		if (!array_intersect($userGroups, $authorized)) {
			throw new LibresignException($this->l10n->t('You are not allowed to request signing'));
		}
	}

	public function iRequestedSignThisFile(IUser $user, int $nodeId): void {
		$libresignFile = $this->fileMapper->getByFileId($nodeId);
		if ($libresignFile->getUserId() !== $user->getUID()) {
			throw new LibresignException($this->l10n->t('You do not have permission for this action.'));
		}
	}

	public function validateFileStatus(array $data): void {
		if (array_key_exists('status', $data)) {
			$validStatusList = [
				File::STATUS_DRAFT,
				File::STATUS_ABLE_TO_SIGN,
				File::STATUS_DELETED
			];
			if (!in_array($data['status'], $validStatusList)) {
				throw new LibresignException($this->l10n->t('Invalid status code for file.'));
			}
			if (!empty($data['uuid'])) {
				$file = $this->fileMapper->getByUuid($data['uuid']);
			} elseif (!empty($data['file']['fileId'])) {
				try {
					$file = $this->fileMapper->getByFileId($data['file']['fileId']);
				} catch (\Throwable $th) {
				}
			}
			if (isset($file)) {
				if ($data['status'] >= $file->getStatus()) {
					if ($file->getStatus() >= File::STATUS_ABLE_TO_SIGN) {
						if ($data['status'] !== File::STATUS_DELETED) {
							throw new LibresignException($this->l10n->t('Sign process already started. Unable to change status.'));
						}
					}
				}
			} elseif ($data['status'] === File::STATUS_DELETED) {
				throw new LibresignException($this->l10n->t('Invalid status code for file.'));
			}
		}
	}

	public function validateExistingFile(array $data): void {
		if (isset($data['uuid'])) {
			$this->validateFileUuid($data);
			$file = $this->fileMapper->getByUuid($data['uuid']);
			$this->iRequestedSignThisFile($data['userManager'], $file->getNodeId());
		} elseif (isset($data['file'])) {
			if (!isset($data['file']['fileId'])) {
				throw new LibresignException($this->l10n->t('Invalid fileID'));
			}
			$this->validateLibreSignNodeId($data['file']['fileId']);
			$this->iRequestedSignThisFile($data['userManager'], $data['file']['fileId']);
		} else {
			throw new LibresignException($this->l10n->t('Inform or UUID or a File object'));
		}
	}

	public function haveValidMail(array $data, ?int $type = null): void {
		if ($type === self::TYPE_TO_SIGN) {
			return;
		}
		if (empty($data)) {
			throw new LibresignException($this->l10n->t('No user data'));
		}
		if (empty($data['email'])) {
			if (!empty($data['uid'])) {
				$user = $this->userManager->get($data['uid']);
				if (!$user) {
					throw new LibresignException($this->l10n->t('User not found.'));
				}
				if (!$user->getEMailAddress()) {
					// TRANSLATORS There is no email address for given user
					throw new LibresignException($this->l10n->t('User %s has no email address.', [$data['uid']]));
				}
			} else {
				throw new LibresignException($this->l10n->t('Email required'));
			}
		} elseif (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			throw new LibresignException($this->l10n->t('Invalid email'));
		}
	}

	public function signerWasAssociated(array $signer): void {
		try {
			$libresignFile = $this->fileMapper->getByFileId();
		} catch (\Throwable $th) {
			throw new LibresignException($this->l10n->t('File not loaded'));
		}
		$signatures = $this->fileUserMapper->getByFileUuid($libresignFile->getUuid());
		$exists = array_filter($signatures, fn ($s) => $s->getEmail() === $signer['email']);
		if (!$exists) {
			throw new LibresignException($this->l10n->t('No signature was requested to %s', $signer['email']));
		}
	}

	public function notSigned(array $signer): void {
		try {
			$libresignFile = $this->fileMapper->getByFileId();
		} catch (\Throwable $th) {
			throw new LibresignException($this->l10n->t('File not loaded'));
		}
		$signatures = $this->fileUserMapper->getByFileUuid($libresignFile->getUuid());
		$exists = array_filter($signatures, fn ($s) => $s->getEmail() === $signer['email'] && $s->getSigned());
		if (!$exists) {
			return;
		}
		$firstSigner = array_values($exists)[0];
		if ($firstSigner->getDisplayName()) {
			throw new LibresignException($this->l10n->t('%s already signed this file', $firstSigner->getDisplayName()));
		}
		throw new LibresignException($this->l10n->t('%s already signed this file', $firstSigner->getDisplayName()));
	}

	public function validateFileUuid(array $data): void {
		try {
			$this->fileMapper->getByUuid($data['uuid']);
		} catch (\Throwable $th) {
			throw new LibresignException($this->l10n->t('Invalid UUID file'));
		}
	}

	public function validateIsSignerOfFile(int $fileUserId, int $fileId): void {
		try {
			$this->fileUserMapper->getByFileIdAndFileUserId($fileId, $fileUserId);
		} catch (\Throwable $th) {
			throw new LibresignException($this->l10n->t('Signer not associated to this file'));
		}
	}

	public function validateUserHasNoFileWithThisType(string $uid, string $type): void {
		try {
			$exists = $this->accountFileMapper->getByUserAndType($uid, $type);
		} catch (\Throwable $th) {
		}
		if (!empty($exists)) {
			throw new LibresignException($this->l10n->t('A file of this type has been associated.'));
		}
	}

	public function canRequestCode(): bool {
		$signMethod = $this->config->getAppValue(Application::APP_ID, 'sign_method', 'password');
		return $signMethod !== 'password';
	}

	public function canSignWithIdentificationDocumentStatus(int $status): void {
		$allowedStatus = [
			FileService::IDENTIFICATION_DOCUMENTS_DISABLED,
			FileService::IDENTIFICATION_DOCUMENTS_APPROVED,
		];
		if (!in_array($status, $allowedStatus)) {
			throw new LibresignException($this->l10n->t('You do not have permission for this action.'));
		}
	}

	public function validateCredentials(FileUser $fileUser, array $params): void {
		$signMethod = $this->config->getAppValue(Application::APP_ID, 'sign_method', 'password');
		switch ($signMethod) {
			case 'sms':
			case 'email':
				$this->valdateCode($fileUser, $params);
				break;
			case 'password':
				if (isset($params['code'])) {
					throw new LibresignException($this->l10n->t('Do not use code when signing method is with password.'));
				}
		}
	}

	public function valdateCode(FileUser $fileUser, array $params): void {
		if (empty($params['code']) || !$this->hasher->verify($params['code'], $fileUser->getCode())) {
			throw new LibresignException($this->l10n->t('Invalid code.'));
		}
		$fileUser->setCode('');
		$this->fileUserMapper->update($fileUser);
	}

	public function validateFileTypeExists(string $type): void {
		$profileFileTypes = $this->fileTypeMapper->getTypes();
		if (!array_key_exists($type, $profileFileTypes)) {
			throw new LibresignException($this->l10n->t('Invalid file type.'));
		}
	}

	public function userCanApproveValidationDocuments(?IUser $user, bool $throw = true): bool {
		if ($user == null) {
			return false;
		}

		$authorized = json_decode($this->config->getAppValue(Application::APP_ID, 'approval_group', '["admin"]'));
		$userGroups = $this->groupManager->getUserGroupIds($user);
		if (!$authorized || !array_intersect($userGroups, $authorized)) {
			if ($throw) {
				throw new LibresignException($this->l10n->t('You are not allowed to approve user profile documents.'));
			}
			return false;
		}
		return true;
	}
}
