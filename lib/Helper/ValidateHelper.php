<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Helper;

use InvalidArgumentException;
use OC\AppFramework\Http;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SignerElementsService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\IHasher;

class ValidateHelper {
	/** @var \OCP\Files\Node[] */
	private $file = [];

	public const TYPE_TO_SIGN = 1;
	public const TYPE_VISIBLE_ELEMENT_PDF = 2;
	public const TYPE_VISIBLE_ELEMENT_USER = 3;
	public const TYPE_ACCOUNT_DOCUMENT = 4;

	public const STATUS_DRAFT = 0;
	public const STATUS_ABLE_TO_SIGN = 1;
	public const STATUS_PARTIAL_SIGNED = 2;
	public const STATUS_SIGNED = 3;
	public const STATUS_DELETED = 4;
	public const VALID_MIMETIPE = [
		'application/pdf',
		'image/png',
	];

	public function __construct(
		private IL10N $l10n,
		private SignRequestMapper $signRequestMapper,
		private FileMapper $fileMapper,
		private FileTypeMapper $fileTypeMapper,
		private FileElementMapper $fileElementMapper,
		private AccountFileMapper $accountFileMapper,
		private UserElementMapper $userElementMapper,
		private IdentifyMethodMapper $identifyMethodMapper,
		private IdentifyMethodService $identifyMethodService,
		private SignerElementsService $signerElementsService,
		private IMimeTypeDetector $mimeTypeDetector,
		private IHasher $hasher,
		private IAppConfig $appConfig,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private IRootFolder $root,
	) {
	}
	public function validateNewFile(array $data, int $type = self::TYPE_TO_SIGN, ?IUser $user = null): void {
		$this->validateFile($data, $type, $user);
		if (!empty($data['file']['fileId'])) {
			$this->validateNotRequestedSign((int)$data['file']['fileId']);
		} elseif (!empty($data['file']['path'])) {
			$userFolder = $this->root->getUserFolder($user?->getUID() ?? $data['userManager']->getUID());
			try {
				$node = $userFolder->get($data['file']['path']);
			} catch (NotFoundException) {
				throw new LibresignException($this->l10n->t('Invalid data to validate file'), 404);
			}
			$this->validateNotRequestedSign($node->getId());
		}
	}

	/**
	 * @property array $data
	 * @property int $type to_sign|visible_element
	 */
	public function validateFile(array $data, int $type = self::TYPE_TO_SIGN, ?IUser $user = null): void {
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
		if (!empty($data['file']['url'])) {
			if (!filter_var($data['file']['url'], FILTER_VALIDATE_URL)) {
				throw new LibresignException($this->l10n->t('File type: %s. Specify a URL, a Base64 string or a fileID.', [$this->getTypeOfFile($type)]));
			}
		} elseif (!empty($data['file']['fileId'])) {
			if (!is_numeric($data['file']['fileId'])) {
				throw new LibresignException($this->l10n->t('File type: %s. Invalid fileID.', [$this->getTypeOfFile($type)]));
			}
			if (!is_a($user, IUser::class)) {
				if (!is_a($data['userManager'], IUser::class)) {
					throw new LibresignException($this->l10n->t('User not found.'));
				}
			}
			$this->validateIfNodeIdExists((int)$data['file']['fileId'], $data['userManager']->getUID(), $type);
			$this->validateMimeTypeAcceptedByNodeId((int)$data['file']['fileId'], $data['userManager']->getUID(), $type);
		} elseif (!empty($data['file']['base64'])) {
			$this->validateBase64($data['file']['base64'], $type);
		} elseif (!empty($data['file']['path'])) {
			if (!is_a($user, IUser::class)) {
				if (!is_a($data['userManager'], IUser::class)) {
					throw new LibresignException($this->l10n->t('User not found.'));
				}
			}
			$userFolder = $this->root->getUserFolder($user?->getUID() ?? $data['userManager']->getUID());
			try {
				$userFolder->get($data['file']['path']);
			} catch (NotFoundException) {
				throw new LibresignException($this->l10n->t('Invalid data to validate file'), 404);
			}
		} else {
			throw new LibresignException($this->l10n->t('File type: %s. Specify a URL, Base64 string, path or a fileID.', [$this->getTypeOfFile($type)]));
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
				throw new LibresignException($this->l10n->t('File type: %s. Invalid Base64 file.', [$this->getTypeOfFile($type)]));
			}
			if ($withMime[0][1] !== 'base64') {
				throw new LibresignException($this->l10n->t('File type: %s. Invalid Base64 file.', [$this->getTypeOfFile($type)]));
			}

			if ($type === self::TYPE_TO_SIGN) {
				if ($withMime[0][0] !== 'data:application/pdf') {
					throw new LibresignException($this->l10n->t('File type: %s. Invalid Base64 file.', [$this->getTypeOfFile($type)]));
				}
			}
			$base64 = $withMime[1];
		}
		$string = base64_decode($base64);
		if (in_array($type, [self::TYPE_VISIBLE_ELEMENT_USER, self::TYPE_VISIBLE_ELEMENT_PDF])) {
			if (strlen($string) > 5000 * 1024) { // 5Mb
				// TRANSLATORS Error when the visible element to add to document, like a signature or initial is bigger than normal
				throw new InvalidArgumentException($this->l10n->t('File is too big'));
			}
		}
		$newBase64 = base64_encode($string);
		if ($newBase64 !== $base64) {
			throw new LibresignException($this->l10n->t('File type: %s. Invalid Base64 file.', [$this->getTypeOfFile($type)]));
		}

		$mimeType = $this->mimeTypeDetector->detectString($string);

		if ($type === self::TYPE_TO_SIGN) {
			if ($mimeType !== 'application/pdf') {
				throw new LibresignException($this->l10n->t('File type: %s. Invalid Base64 file.', [$this->getTypeOfFile($type)]));
			}
		} elseif ($mimeType !== 'image/png') {
			if (in_array($type, [self::TYPE_VISIBLE_ELEMENT_USER, self::TYPE_VISIBLE_ELEMENT_PDF])) {
				throw new LibresignException($this->l10n->t('File type: %s. Invalid Base64 file.', [$this->getTypeOfFile($type)]));
			}
		}
	}

	public function validateNotRequestedSign(int $nodeId): void {
		try {
			$fileMapper = $this->signRequestMapper->getByNodeId($nodeId);
		} catch (\Throwable) {
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
		$this->validateElementSignRequestId($element, $type);
		$this->validateFile($element, $type);
		$this->validateElementCoordinates($element);
	}

	public function validateElementSignRequestId(array $element, int $type): void {
		if ($type !== self::TYPE_VISIBLE_ELEMENT_PDF) {
			return;
		}
		if (!array_key_exists('signRequestId', $element)) {
			// TRANSLATION The element can be an image or text. It has to be associated with an user. The element will be added to the document.
			throw new LibresignException($this->l10n->t('Element must be associated with a user'));
		}
		try {
			$this->signRequestMapper->getById($element['signRequestId']);
		} catch (\Throwable) {
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

	private function validateElementCoordinate(array $element): void {
		foreach ($element['coordinates'] as $type => $value) {
			if (in_array($type, ['llx', 'lly', 'urx', 'ury', 'width', 'height', 'left', 'top'])) {
				if (!is_int($value)) {
					throw new LibresignException($this->l10n->t('Coordinate %s must be an integer', [$type]));
				}
				if ($value < 0) {
					// TRANSLATORS Is an error that occur when the visible element added to the PDF file have your position outside the page margin
					throw new LibresignException($this->l10n->t('Object outside the page margin'));
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

	public function validateVisibleElementsRelation(array $list, SignRequest $signRequest, ?IUser $user): void {
		$canCreateSignature = $this->signerElementsService->canCreateSignature();
		foreach ($list as $elements) {
			if (!array_key_exists('documentElementId', $elements)) {
				throw new LibresignException($this->l10n->t('Field %s not found', ['documentElementId']));
			}
			if ($canCreateSignature && !array_key_exists('profileNodeId', $elements)) {
				throw new LibresignException($this->l10n->t('Field %s not found', ['profileNodeId']));
			}
			$this->validateSignerIsOwnerOfPdfVisibleElement($elements['documentElementId'], $signRequest);
			if ($canCreateSignature && $user instanceof IUser) {
				try {
					$this->userElementMapper->findOne(['file_id' => $elements['profileNodeId'], 'user_id' => $user->getUID()]);
				} catch (\Throwable) {
					throw new LibresignException($this->l10n->t('Field %s does not belong to user', $elements['profileNodeId']));
				}
			}
		}
		$this->validateUserHasNecessaryElements($signRequest, $user, $list);
	}

	private function validateUserHasNecessaryElements(SignRequest $signRequest, ?IUser $user, array $list = []): void {
		$fileElements = $this->fileElementMapper->getByFileIdAndSignRequestId($signRequest->getFileId(), $signRequest->getId());
		$total = array_filter($fileElements, function (FileElement $fileElement) use ($list, $user): bool {
			$found = array_filter($list, fn ($item): bool => $item['documentElementId'] === $fileElement->getId());
			if (!$found) {
				if (!$this->signerElementsService->canCreateSignature()) {
					return true;
				}
				try {
					if (!$user instanceof IUser) {
						throw new \Exception();
					}
					$this->userElementMapper->findMany([
						'user_id' => $user->getUID(),
						'type' => $fileElement->getType(),
					]);
					return true;
				} catch (\Throwable) {
					throw new LibresignException($this->l10n->t('You need to define a visible signature or initials to sign this document.'));
				}
			}
			return true;
		});
		if (count($total) !== count($fileElements)) {
			throw new LibresignException($this->l10n->t('You need to define a visible signature or initials to sign this document.'));
		}
	}

	private function validateSignerIsOwnerOfPdfVisibleElement(int $documentElementId, SignRequest $signRequest): void {
		$documentElement = $this->fileElementMapper->getById($documentElementId);
		if ($documentElement->getSignRequestId() !== $signRequest->getId()) {
			throw new LibresignException($this->l10n->t('Invalid data to sign file'), 1);
		}
	}

	public function validateAuthenticatedUserIsOwnerOfPdfVisibleElement(int $documentElementId, string $uid): void {
		try {
			$documentElement = $this->fileElementMapper->getById($documentElementId);
			$signRequest = $this->signRequestMapper->getById($documentElement->getSignRequestId());
			$file = $this->fileMapper->getById($signRequest->getFileId());
			if ($file->getUserId() !== $uid) {
				throw new LibresignException($this->l10n->t('Field %s does not belong to user', (string)$documentElementId));
			}
		} catch (\Throwable) {
			($signRequest->getFileId());
			throw new LibresignException($this->l10n->t('Field %s does not belong to user', (string)$documentElementId));
		}
	}

	public function validateAccountFileIsOwnedByUser(int $nodeId, string $uid): void {
		try {
			$this->accountFileMapper->getByUserIdAndNodeId($uid, $nodeId);
		} catch (\Throwable) {
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

	public function validateIfNodeIdExists(int $nodeId, string $userId = '', int $type = self::TYPE_TO_SIGN): void {
		if (!$userId) {
			$libresignFile = $this->fileMapper->getByFileId($nodeId);
			$userId = $libresignFile->getUserId();
		}
		try {
			$file = $this->root->getUserFolder($userId)->getById($nodeId);
			$file = $file[0] ?? null;
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('File type: %s. Invalid fileID.', [$this->getTypeOfFile($type)]));
		}
		if (!$file) {
			throw new LibresignException($this->l10n->t('File type: %s. Invalid fileID.', [$this->getTypeOfFile($type)]));
		}
	}

	public function validateMimeTypeAcceptedByNodeId(int $nodeId, string $userId = '', int $type = self::TYPE_TO_SIGN): void {
		if (!$userId) {
			$libresignFile = $this->fileMapper->getByFileId($nodeId);
			$userId = $libresignFile->getUserId();
		}
		$file = $this->root->getUserFolder($userId)->getById($nodeId);
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
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('Invalid fileID'));
		}
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 * @return \OCP\Files\Node|array
	 * @psalm-return \OCP\Files\Node|array<empty, empty>
	 */
	private function getLibreSignFileByNodeId(int $nodeId) {
		if (isset($this->file[$nodeId])) {
			return $this->file[$nodeId];
		}
		$libresignFile = $this->fileMapper->getByFileId($nodeId);

		$userFolder = $this->root->getUserFolder($libresignFile->getUserId());
		$files = $userFolder->getById($nodeId);
		if (!empty($files)) {
			$this->file[$nodeId] = $files[0];
			return $this->file[$nodeId];
		}
		return [];
	}

	public function canRequestSign(IUser $user): void {
		$authorized = $this->appConfig->getValueArray(Application::APP_ID, 'groups_request_sign', ['admin']);
		if (empty($authorized)) {
			$authorized = ['admin'];
		}
		if (!is_array($authorized)) {
			throw new LibresignException(
				json_encode([
					'action' => JSActions::ACTION_DO_NOTHING,
					'errors' => [['message' => $this->l10n->t('You are not allowed to request signing')]],
				]),
				Http::STATUS_UNPROCESSABLE_ENTITY,
			);
		}
		$userGroups = $this->groupManager->getUserGroupIds($user);
		if (!array_intersect($userGroups, $authorized)) {
			throw new LibresignException(
				json_encode([
					'action' => JSActions::ACTION_DO_NOTHING,
					'errors' => [['message' => $this->l10n->t('You are not allowed to request signing')]],
				]),
				Http::STATUS_UNPROCESSABLE_ENTITY,
			);
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
				} catch (\Throwable) {
				}
			}
			if (isset($file)) {
				if ($data['status'] > $file->getStatus()) {
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
			// TRANSLATORS This message is at API side. When an application or a
			// developer send a structure to API without an UUID or without a
			// File Object, throws this error. Normally LibreSign don't throws
			// this error because the User Interface of LibreSign or send an
			// UUID or a File object to API.
			throw new LibresignException($this->l10n->t('Please provide either UUID or File object'));
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
		} elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			throw new LibresignException($this->l10n->t('Invalid email'));
		}
	}

	public function signerWasAssociated(array $signer): void {
		try {
			$libresignFile = $this->fileMapper->getByFileId();
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('File not loaded'));
		}
		$signatures = $this->signRequestMapper->getByFileUuid($libresignFile->getUuid());
		$exists = array_filter($signatures, function (SignRequest $signRequest) use ($signer): bool {
			$key = key($signer);
			$value = current($signer);
			$identifyMethods = $this->identifyMethodMapper->getIdentifyMethodsFromSignRequestId($signRequest->getId());
			$found = array_filter($identifyMethods, function (IdentifyMethod $identifyMethod) use ($key, $value) {
				if ($identifyMethod->getIdentifierKey() === $key && $identifyMethod->getIdentifierValue() === $value) {
					return true;
				}
				return false;
			});
			return count($found) > 0;
		});
		if (!$exists) {
			throw new LibresignException($this->l10n->t('No signature was requested to %s', $signer['email']));
		}
	}

	public function notSigned(array $signer): void {
		try {
			$libresignFile = $this->fileMapper->getByFileId();
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('File not loaded'));
		}
		$signatures = $this->signRequestMapper->getByFileUuid($libresignFile->getUuid());

		$exists = array_filter($signatures, function (SignRequest $signRequest) use ($signer): bool {
			$key = key($signer);
			$value = current($signer);
			$identifyMethods = $this->identifyMethodMapper->getIdentifyMethodsFromSignRequestId($signRequest->getId());
			$found = array_filter($identifyMethods, function (IdentifyMethod $identifyMethod) use ($key, $value) {
				if ($identifyMethod->getIdentifierKey() === $key && $identifyMethod->getIdentifierValue() === $value) {
					return true;
				}
				return false;
			});
			if (count($found) > 0) {
				return $signRequest->getSigned() !== null;
			}
			return false;
		});
		if (!$exists) {
			return;
		}
		$firstSigner = array_values($exists)[0];
		throw new LibresignException($this->l10n->t('%s already signed this file', $firstSigner->getDisplayName()));
	}

	public function validateFileUuid(array $data): void {
		try {
			$this->fileMapper->getByUuid($data['uuid']);
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('Invalid UUID file'));
		}
	}

	public function validateSigner(string $uuid, ?IUser $user = null): void {
		$this->validateSignerUuidExists($uuid);
		$this->validateIdentifyMethod($uuid, $user);
	}

	public function validateRenewSigner(string $uuid, ?IUser $user = null): void {
		$this->validateSignerUuidExists($uuid);
		$signRequest = $this->signRequestMapper->getByUuid($uuid);
		$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
		foreach ($identifyMethods as $methods) {
			foreach ($methods as $identifyMethod) {
				$identifyMethod->validateToRenew($user);
			}
		}
	}

	private function validateIdentifyMethod(string $uuid, ?IUser $user = null): void {
		$signRequest = $this->signRequestMapper->getByUuid($uuid);
		$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
		foreach ($identifyMethods as $methods) {
			foreach ($methods as $identifyMethod) {
				$identifyMethod->validateToIdentify();
			}
		}
	}

	private function validateSignerUuidExists(string $uuid): void {
		$this->validateUuidFormat($uuid);
		try {
			$signRequest = $this->signRequestMapper->getByUuid($uuid);
			$this->fileMapper->getById($signRequest->getFileId());
		} catch (DoesNotExistException) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [['message' => $this->l10n->t('Invalid UUID')]],
			]));
		}
	}

	/**
	 * @throws LibresignException
	 */
	public function validateUuidFormat(string $uuid): void {
		if (!$uuid || !preg_match('/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i', $uuid)) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [['message' => $this->l10n->t('Invalid UUID')]],
			]), Http::STATUS_NOT_FOUND);
		}
	}

	public function validateIsSignerOfFile(int $signRequestId, int $fileId): void {
		try {
			$this->signRequestMapper->getByFileIdAndSignRequestId($fileId, $signRequestId);
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('Signer not associated to this file'));
		}
	}

	public function validateUserHasNoFileWithThisType(string $uid, string $type): void {
		try {
			$exists = $this->accountFileMapper->getByUserAndType($uid, $type);
		} catch (\Throwable) {
		}
		if (!empty($exists)) {
			throw new LibresignException($this->l10n->t('A file of this type has been associated.'));
		}
	}

	public function canSignWithIdentificationDocumentStatus(?IUser $user, int $status): void {
		// User that can approve validation documents don't need to have a valid
		// document attached to their profile. If this were required, nobody
		// would be able to sign any document
		if ($this->userCanApproveValidationDocuments($user, false)) {
			return;
		}
		$allowedStatus = [
			FileService::IDENTIFICATION_DOCUMENTS_DISABLED,
			FileService::IDENTIFICATION_DOCUMENTS_APPROVED,
		];
		if (!in_array($status, $allowedStatus)) {
			throw new LibresignException($this->l10n->t('You do not have permission for this action.'));
		}
	}

	public function validateCredentials(SignRequest $signRequest, string $identifyMethodName, string $identifyValue, string $token): void {
		$this->validateIfIdentifyMethodExists($identifyMethodName);
		if ($signRequest->getSigned()) {
			throw new LibresignException($this->l10n->t('File already signed.'));
		}
		$identifyMethod = $this->resolveIdentifyMethod($signRequest, $identifyMethodName, $identifyValue);
		$identifyMethod->setCodeSentByUser($token);
		$identifyMethod->validateToSign();
	}

	private function resolveIdentifyMethod(SignRequest $signRequest, string $methodName, ?string $identifyValue): IIdentifyMethod {
		if (!$signRequest->getId()) {
			return $this->identifyMethodService
				->setCurrentIdentifyMethod()
				->getInstanceOfIdentifyMethod($methodName, $identifyValue);
		}

		$methodsList = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
		$identifyMethod = $this->searchMethodByNameAndValue($methodsList, $methodName, $identifyValue);
		if ($identifyMethod) {
			return $identifyMethod;
		}

		$signMethods = $this->identifyMethodService->getSignMethodsOfIdentifiedFactors($signRequest->getId());
		$identifyMethod = $this->searchMethodByNameAndValue($signMethods, $methodName, $identifyValue);
		if ($identifyMethod) {
			return $identifyMethod;
		}

		if (!empty($methodsList)) {
			return $this->getFirstAvailableMethod($methodsList);
		}

		if (!empty($signMethods)) {
			return $this->getFirstAvailableMethod($signMethods);
		}

		throw new LibresignException($this->l10n->t('Invalid identification method'));
	}

	private function searchMethodByNameAndValue(array $methods, string $methodName, ?string $identifyValue): ?IIdentifyMethod {
		if (isset($methods[$methodName])) {
			if ($identifyValue) {
				foreach ($methods[$methodName] as $identifyMethod) {
					if (!$identifyMethod instanceof IIdentifyMethod) {
						$identifyMethod = $this->getIdentifyMethodByNameAndValue($methodName, $identifyValue);
					}
					if ($identifyMethod->getEntity()->getIdentifierValue() === $identifyValue) {
						return $identifyMethod;
					}
				}
			} else {
				$identifyMethod = current($methods[$methodName]);
				if (!$identifyMethod instanceof IIdentifyMethod) {
					$identifyMethod = $this->getIdentifyMethodByNameAndValue($methodName, $identifyValue);
				}
				return $identifyMethod;
			}
		}

		return null;
	}

	private function getIdentifyMethodByNameAndValue(string $identifyMethodName, ?string $identifyValue): IIdentifyMethod {
		return $this->identifyMethodService
			->setCurrentIdentifyMethod()
			->getInstanceOfIdentifyMethod($identifyMethodName, $identifyValue);
	}

	private function getFirstAvailableMethod(array $methods): IIdentifyMethod {
		foreach ($methods as $methodGroup) {
			if (!empty($methodGroup)) {
				return current($methodGroup);
			}
		}
		throw new LibresignException($this->l10n->t('Invalid identification method'));
	}

	public function validateIfIdentifyMethodExists(string $identifyMethod): void {
		if (!in_array($identifyMethod, IdentifyMethodService::IDENTIFY_METHODS)) {
			// TRANSLATORS When is requested to a person to sign a file, is
			// necessary identify what is the identification method. The
			// identification method is used to define how will be the sign
			// flow.
			throw new LibresignException($this->l10n->t('Invalid identification method'));
		}
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

		$authorized = $this->appConfig->getValueArray(Application::APP_ID, 'approval_group', ['admin']);
		if (!$authorized || !is_array($authorized) || empty($authorized)) {
			$authorized = ['admin'];
		}
		$userGroups = $this->groupManager->getUserGroupIds($user);
		if (!array_intersect($userGroups, $authorized)) {
			if ($throw) {
				throw new LibresignException($this->l10n->t('You are not allowed to approve user profile documents.'));
			}
			return false;
		}
		return true;
	}
}
