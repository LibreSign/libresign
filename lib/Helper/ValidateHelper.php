<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Helper;

use InvalidArgumentException;
use OC\AppFramework\Http;
use OC\User\NoUserException;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\ParticipantRole;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\DocMdp\Validator as DocMdpValidator;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\IdDocsPolicyService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethod\RuntimeRequirementValidator;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\RequestSignAuthorizationService;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\ObserverProfile\ObserverProfilePolicy;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Service\SignerElementsService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;

class ValidateHelper {
	/** @var \OCP\Files\File[] */
	private $file = [];

	public const TYPE_TO_SIGN = 1;
	public const TYPE_VISIBLE_ELEMENT_PDF = 2;
	public const TYPE_VISIBLE_ELEMENT_USER = 3;
	public const TYPE_ACCOUNT_DOCUMENT = 4;
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
		private IdDocsMapper $idDocsMapper,
		private UserElementMapper $userElementMapper,
		private IdentifyMethodService $identifyMethodService,
		private SequentialSigningService $sequentialSigningService,
		private SignerElementsService $signerElementsService,
		private IMimeTypeDetector $mimeTypeDetector,
		private IdDocsPolicyService $idDocsPolicyService,
		private IUserManager $userManager,
		private IRootFolder $root,
		private DocMdpValidator $docMdpValidator,
		private RequestSignAuthorizationService $requestSignAuthorizationService,
		private RuntimeRequirementValidator $runtimeRequirementValidator,
		private PolicyService $policyService,
	) {
	}

	public function validateNewFile(array $data, int $type = self::TYPE_TO_SIGN, ?IUser $user = null): void {
		$this->validateFile($data, $type, $user);
		if (!empty($data['file']['nodeId'])) {
			$this->validateNotRequestedSign((int)$data['file']['nodeId']);
		} elseif (!empty($data['file']['path'])) {
			$userFolder = $this->root->getUserFolder($user?->getUID() ?? $data['userManager']->getUID());
			try {
				$node = $userFolder->get($data['file']['path']);
			} catch (NotFoundException) {
				// TRANSLATORS Validation error when LibreSign cannot validate the uploaded or referenced file payload for signing.
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
			if (!empty($data['files'])) {
				foreach ($data['files'] as $fileItem) {
					$this->validateFile([
						'file' => $fileItem,
						'userManager' => $data['userManager'] ?? null,
						'type' => $data['type'] ?? null,
					], $type, $user);
				}
				return;
			}
			if ($type === self::TYPE_TO_SIGN) {
				// TRANSLATORS Validation error shown when the API expected a document to be signed but received an empty file payload. %s is the localized file-role label such as "document to sign".
				throw new LibresignException($this->l10n->t('File type: %s. Empty file.', [$this->getTypeOfFile($type)]));
			}
			if ($type === self::TYPE_VISIBLE_ELEMENT_USER) {
				if ($this->elementNeedFile($data)) {
					// TRANSLATORS Validation error shown when a visible signature element (for example a handwritten signature image) requires an uploaded file. %s is the requested visible element type.
					throw new LibresignException($this->l10n->t('Elements of type %s need file.', [$data['type']]));
				}
			}
			return;
		}
		if (!empty($data['file']['url'])) {
			if (!filter_var($data['file']['url'], FILTER_VALIDATE_URL)) {
				// TRANSLATORS Validation error shown when the caller must provide a valid URL, Base64 payload, or file identifier. %s is the localized file-role label.
				throw new LibresignException($this->l10n->t('File type: %s. Specify a URL, a Base64 string or a fileID.', [$this->getTypeOfFile($type)]));
			}
		} elseif (!empty($data['file']['nodeId'])) {
			if (!is_numeric($data['file']['nodeId'])) {
				throw new LibresignException($this->l10n->t('File type: %s. Invalid fileID.', [$this->getTypeOfFile($type)]));
			}
			if (!is_a($user, IUser::class)) {
				if (!isset($data['userManager']) || !is_a($data['userManager'], IUser::class)) {
					// TRANSLATORS Validation error when the Nextcloud user associated with a file or signature request cannot be found.
					throw new LibresignException($this->l10n->t('User not found.'));
				}
			}
			$this->validateIfNodeIdExists((int)$data['file']['nodeId'], $data['userManager']->getUID(), $type);
			$this->validateMimeTypeAcceptedByNodeId((int)$data['file']['nodeId'], $data['userManager']->getUID(), $type);
		} elseif (!empty($data['file']['fileId']) && $type === self::TYPE_VISIBLE_ELEMENT_PDF) {
			if (!is_numeric($data['file']['fileId'])) {
				// TRANSLATORS Validation error when a Nextcloud fileID used for a LibreSign file role is invalid. %s is the localized file-role label.
				throw new LibresignException($this->l10n->t('File type: %s. Invalid fileID.', [$this->getTypeOfFile($type)]));
			}
			$this->validateLibreSignFileId((int)$data['file']['fileId']);
		} elseif (!empty($data['file']['base64'])) {
			$this->validateBase64($data['file']['base64'], $type);
		} elseif (!empty($data['file']['path'])) {
			if (!is_a($user, IUser::class)) {
				if (!is_a($data['userManager'], IUser::class)) {
					// TRANSLATORS Validation error when the Nextcloud user associated with a file or signature request cannot be found.
					throw new LibresignException($this->l10n->t('User not found.'));
				}
			}
			$userFolder = $this->root->getUserFolder($user?->getUID() ?? $data['userManager']->getUID());
			try {
				$userFolder->get($data['file']['path']);
			} catch (NotFoundException) {
				// TRANSLATORS Validation error when LibreSign cannot validate the uploaded or referenced file payload for signing.
				throw new LibresignException($this->l10n->t('Invalid data to validate file'), 404);
			}
		} else {
			// TRANSLATORS Validation error listing accepted ways to provide a file for a signature request. %s is the localized file-role label.
			throw new LibresignException($this->l10n->t('File type: %s. Specify a URL, Base64 string, path or a fileID.', [$this->getTypeOfFile($type)]));
		}
	}

	private function elementNeedFile(array $data): bool {
		return in_array($data['type'], ['signature', 'initial']);
	}

	private function getTypeOfFile(int $type): string {
		if ($type === self::TYPE_TO_SIGN) {
			// TRANSLATORS File-role label used inside validation errors to mean the PDF document that will receive signatures.
			return $this->l10n->t('document to sign');
		}
		// TRANSLATORS File-role label used inside validation errors for a visible signature asset such as a signature image or initials image.
		return $this->l10n->t('visible signature element');
	}

	public function validateBase64(string $base64, int $type = self::TYPE_TO_SIGN): void {
		$withMime = explode(',', $base64);
		if (count($withMime) === 2) {
			$withMime[0] = explode(';', $withMime[0]);
			if (count($withMime[0]) !== 2) {
				// TRANSLATORS Validation error when a Base64-encoded file payload for a signature request is invalid. %s is the localized file-role label.
				throw new LibresignException($this->l10n->t('File type: %s. Invalid Base64 file.', [$this->getTypeOfFile($type)]));
			}
			if ($withMime[0][1] !== 'base64') {
				// TRANSLATORS Validation error when a Base64-encoded file payload for a signature request is invalid. %s is the localized file-role label.
				throw new LibresignException($this->l10n->t('File type: %s. Invalid Base64 file.', [$this->getTypeOfFile($type)]));
			}

			if ($type === self::TYPE_TO_SIGN) {
				if ($withMime[0][0] !== 'data:application/pdf') {
					// TRANSLATORS Validation error when a Base64-encoded file payload for a signature request is invalid. %s is the localized file-role label.
					throw new LibresignException($this->l10n->t('File type: %s. Invalid Base64 file.', [$this->getTypeOfFile($type)]));
				}
			}
			$base64 = $withMime[1];
		}
		$string = base64_decode($base64);
		if (in_array($type, [self::TYPE_VISIBLE_ELEMENT_USER, self::TYPE_VISIBLE_ELEMENT_PDF])) {
			if (strlen($string) > 5000 * 1024) { // 5Mb
				// TRANSLATORS Error shown when a visible signature asset (for example a signature or initials image) exceeds the allowed upload size.
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
				// TRANSLATORS Validation error when a Base64-encoded file payload for a signature request is invalid. %s is the localized file-role label.
				throw new LibresignException($this->l10n->t('File type: %s. Invalid Base64 file.', [$this->getTypeOfFile($type)]));
			}
		} elseif ($mimeType !== 'image/png') {
			if (in_array($type, [self::TYPE_VISIBLE_ELEMENT_USER, self::TYPE_VISIBLE_ELEMENT_PDF])) {
				// TRANSLATORS Validation error when a Base64-encoded file payload for a signature request is invalid. %s is the localized file-role label.
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
			// TRANSLATORS Validation error when a signature was already requested for this document and a duplicate request is blocked.
			throw new LibresignException($this->l10n->t('Already asked to sign this document'));
		}
	}

	public function validateVisibleElements(?array $visibleElements, int $type): void {
		if (!is_array($visibleElements)) {
			// TRANSLATORS Validation error when visible signature elements must be sent as a JSON array and another type was provided.
			throw new LibresignException($this->l10n->t('Visible elements need to be an array'));
		}
		if ($visibleElements && !$this->signerElementsService->isSignElementsAvailable()) {
			// TRANSLATORS Validation error shown when visible signature elements are sent while the feature is disabled by the administrator.
			throw new LibresignException($this->l10n->t('Visible elements are disabled.'));
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
		if (!array_key_exists('signRequestId', $element) && !array_key_exists('uuid', $element)) {
			// TRANSLATORS Validation error shown when a visible element (image or text placed on the document) is missing the signer association it belongs to.
			throw new LibresignException($this->l10n->t('Element must be associated with a user'));
		}

		$getter = array_key_exists('signRequestId', $element)
			? fn () => $this->signRequestMapper->getById($element['signRequestId'])
			: fn () => $this->signRequestMapper->getByUuid($element['uuid']);

		try {
			$getter();
		} catch (\Throwable) {
			// TRANSLATORS Validation error when a visible signature element references a signer user that does not exist.
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
					// TRANSLATORS Validation error for visible signature element placement. %s is the coordinate name such as urx, ury, llx, or lly.
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
			// TRANSLATORS Validation error when the PDF page number for a visible signature element is not an integer.
			throw new LibresignException($this->l10n->t('Page number must be an integer'));
		}
		if ($element['coordinates']['page'] < 1) {
			// TRANSLATORS Validation error when the PDF page number for a visible signature element is less than 1.
			throw new LibresignException($this->l10n->t('Page must be equal to or greater than 1'));
		}
	}

	public function validateElementType(array $element): void {
		if (!array_key_exists('type', $element)) {
			if (!array_key_exists('elementId', $element)) {
				// TRANSLATORS Validation error when a visible signature element is missing its type (signature, initials, or similar).
				throw new LibresignException($this->l10n->t('Element needs a type'));
			}
			return;
		}
		if (!in_array($element['type'], ['signature', 'initial', 'date', 'datetime', 'text'])) {
			// TRANSLATORS Validation error when a visible signature element uses an unsupported type.
			throw new LibresignException($this->l10n->t('Invalid element type'));
		}
	}

	public function validateVisibleElementsRelation(array $list, SignRequest $signRequest, ?IUser $user): void {
		$canCreateSignature = $this->signerElementsService->canCreateSignature();
		$childSignRequests = $this->getEnvelopeChildSignRequests($signRequest);
		$childSignRequestIds = array_map(fn (SignRequest $sr) => $sr->getId(), $childSignRequests);
		foreach ($list as $elements) {
			if (!array_key_exists('documentElementId', $elements)) {
				// TRANSLATORS Validation error when a required field is missing from the visible element payload. %s is the field name documentElementId.
				throw new LibresignException($this->l10n->t('Field %s not found', ['documentElementId']));
			}
			if ($canCreateSignature && !array_key_exists('profileNodeId', $elements)) {
				// TRANSLATORS Validation error when a required field is missing from the visible element payload. %s is the field name profileNodeId.
				throw new LibresignException($this->l10n->t('Field %s not found', ['profileNodeId']));
			}
			$this->validateSignerIsOwnerOfPdfVisibleElement($elements['documentElementId'], $signRequest, $childSignRequestIds);
			if ($canCreateSignature && $user instanceof IUser) {
				try {
					$this->userElementMapper->findOne(['node_id' => $elements['profileNodeId'], 'user_id' => $user->getUID()]);
				} catch (\Throwable) {
					// TRANSLATORS Validation error when a visible element profile file does not belong to the current user. %s is the profile node id.
					throw new LibresignException($this->l10n->t('Field %s does not belong to user', $elements['profileNodeId']));
				}
			}
		}
		$this->validateUserHasNecessaryElements($signRequest, $user, $list, $childSignRequests);
	}

	/**
	 * @return SignRequest[]
	 */
	private function getEnvelopeChildSignRequests(SignRequest $signRequest): array {
		$file = $this->fileMapper->getById($signRequest->getFileId());
		if (!$file->isEnvelope()) {
			return [];
		}
		return $this->signRequestMapper->getByEnvelopeChildrenAndIdentifyMethod(
			$file->getId(),
			$signRequest->getId()
		);
	}

	/**
	 * @param SignRequest[] $childSignRequests
	 */
	private function validateUserHasNecessaryElements(
		SignRequest $signRequest,
		?IUser $user,
		array $list = [],
		array $childSignRequests = [],
	): void {
		$fileElements = $this->fileElementMapper->getByFileIdAndSignRequestId($signRequest->getFileId(), $signRequest->getId());
		if (empty($fileElements) && !empty($childSignRequests)) {
			foreach ($childSignRequests as $childSr) {
				$fileElements = array_merge(
					$fileElements,
					$this->fileElementMapper->getByFileIdAndSignRequestId($childSr->getFileId(), $childSr->getId())
				);
			}
		}
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
					// TRANSLATORS Validation error when signing requires a visible signature or initials image and the signer has not defined one yet.
					throw new LibresignException($this->l10n->t('You need to define a visible signature or initials to sign this document.'));
				}
			}
			return true;
		});
		if (count($total) !== count($fileElements)) {
			// TRANSLATORS Validation error when signing requires a visible signature or initials image and the signer has not defined one yet.
			throw new LibresignException($this->l10n->t('You need to define a visible signature or initials to sign this document.'));
		}
	}

	/**
	 * @param int[] $childSignRequestIds
	 */
	private function validateSignerIsOwnerOfPdfVisibleElement(int $documentElementId, SignRequest $signRequest, array $childSignRequestIds = []): void {
		$documentElement = $this->fileElementMapper->getById($documentElementId);
		if ($documentElement->getSignRequestId() === $signRequest->getId()) {
			return;
		}
		if (!empty($childSignRequestIds) && in_array($documentElement->getSignRequestId(), $childSignRequestIds, true)) {
			return;
		}
		// TRANSLATORS Validation error when the data required to apply a digital signature is incomplete or invalid.
		throw new LibresignException($this->l10n->t('Invalid data to sign file'), 1);
	}

	public function validateAuthenticatedUserIsOwnerOfPdfVisibleElement(int $documentElementId, string $uid): void {
		try {
			$documentElement = $this->fileElementMapper->getById($documentElementId);
			$signRequest = $this->signRequestMapper->getById($documentElement->getSignRequestId());
			$file = $this->fileMapper->getById($signRequest->getFileId());
			if ($file->getUserId() !== $uid) {
				// TRANSLATORS Validation error when a visible document element does not belong to the current user. %s is the element id.
				throw new LibresignException($this->l10n->t('Field %s does not belong to user', (string)$documentElementId));
			}
		} catch (\Throwable) {
			// TRANSLATORS Validation error when a visible document element does not belong to the current user. %s is the element id.
			throw new LibresignException($this->l10n->t('Field %s does not belong to user', (string)$documentElementId));
		}
	}

	public function validateIdDocIsOwnedByUser(int $nodeId, string $uid): void {
		try {
			$this->idDocsMapper->getByUserIdAndNodeId($uid, $nodeId);
		} catch (\Throwable) {
			// TRANSLATORS Validation error when the user tries to manage a LibreSign file they do not own.
			throw new LibresignException($this->l10n->t('This file is not yours'));
		}
	}

	public function validateIdDocBelongsToSignRequest(int $nodeId, int $signRequestId): void {
		try {
			$this->idDocsMapper->getBySignRequestIdAndNodeId($signRequestId, $nodeId);
		} catch (\Throwable) {
			// TRANSLATORS Generic permission error when the current user cannot perform the requested LibreSign action.
			throw new LibresignException($this->l10n->t('Not allowed'));
		}
	}

	public function fileCanBeSigned(File $file): void {
		$statusList = [
			FileStatus::ABLE_TO_SIGN->value,
			FileStatus::PARTIAL_SIGNED->value
		];
		if (!in_array($file->getStatus(), $statusList)) {
			$statusText = $this->fileMapper->getTextOfStatus($file->getStatus());
			// TRANSLATORS Validation error when a document cannot be signed because its LibreSign status is not signable. %s is the status label.
			throw new LibresignException($this->l10n->t('This file cannot be signed. Invalid status: %s', $statusText));
		}
	}

	public function validateIfNodeIdExists(int $nodeId, string $userId = '', int $type = self::TYPE_TO_SIGN): void {
		if (!$userId) {
			$libresignFile = $this->fileMapper->getByNodeId($nodeId);
			$userId = $libresignFile->getUserId();
		}
		try {
			$file = $this->root->getUserFolder($userId)->getFirstNodeById($nodeId);
		} catch (NoUserException) {
			// TRANSLATORS Validation error when the Nextcloud user associated with a file or signature request cannot be found.
			throw new LibresignException($this->l10n->t('User not found.'));
		} catch (NotPermittedException) {
			// TRANSLATORS Permission error when the current user cannot perform the requested LibreSign file action.
			throw new LibresignException($this->l10n->t('You do not have permission for this action.'));
		}
		if (!$file) {
			// TRANSLATORS Validation error when a Nextcloud fileID used for a LibreSign file role is invalid. %s is the localized file-role label.
			throw new LibresignException($this->l10n->t('File type: %s. Invalid fileID.', [$this->getTypeOfFile($type)]));
		}
	}

	public function validateMimeTypeAcceptedByNodeId(int $nodeId, string $userId = '', int $type = self::TYPE_TO_SIGN): void {
		if (!$userId) {
			$libresignFile = $this->fileMapper->getByNodeId($nodeId);
			$userId = $libresignFile->getUserId();
		}
		$file = $this->root->getUserFolder($userId)->getFirstNodeById($nodeId);
		$this->validateMimeTypeAcceptedByMime($file->getMimeType(), $type);
	}

	public function validateMimeTypeAcceptedByMime(string $mimetype, int $type = self::TYPE_TO_SIGN): void {
		switch ($type) {
			case self::TYPE_TO_SIGN:
				if ($mimetype !== 'application/pdf') {
					// TRANSLATORS Validation error when a fileID must point to a PDF for the given LibreSign file role. First %s is the role label; second %s is the expected format.
					throw new LibresignException($this->l10n->t('File type: %s. Must be a fileID of %s format.', [$this->getTypeOfFile($type), 'PDF']));
				}
				break;
			case self::TYPE_VISIBLE_ELEMENT_PDF:
			case self::TYPE_VISIBLE_ELEMENT_USER:
				if ($mimetype !== 'image/png') {
					// TRANSLATORS Validation error when a fileID must point to a PNG for the given LibreSign file role. First %s is the role label; second %s is the expected format.
					throw new LibresignException($this->l10n->t('File type: %s. Must be a fileID of %s format.', [$this->getTypeOfFile($type), 'png']));
				}
				break;
		}
	}

	public function validateLibreSignFileId(int $fileId): void {
		try {
			$this->fileMapper->getById($fileId);
		} catch (\Throwable) {
			// TRANSLATORS Validation error when the provided Nextcloud fileID is invalid for LibreSign.
			throw new LibresignException($this->l10n->t('Invalid fileID'));
		}
	}

	private function getLibreSignFileByNodeId(int $nodeId): ?\OCP\Files\File {
		if (isset($this->file[$nodeId])) {
			return $this->file[$nodeId];
		}
		$libresignFile = $this->fileMapper->getByNodeId($nodeId);

		$userFolder = $this->root->getUserFolder($libresignFile->getUserId());
		$file = $userFolder->getFirstNodeById($nodeId);
		if ($file instanceof \OCP\Files\File) {
			$this->file[$nodeId] = $file;
			return $this->file[$nodeId];
		}
		return null;
	}

	public function canRequestSign(IUser $user): void {
		if (!$this->requestSignAuthorizationService->canRequestSign($user)) {
			throw new LibresignException(
				json_encode([
					'action' => JSActions::ACTION_DO_NOTHING,
					// TRANSLATORS Permission error when the user is not allowed to create new signature requests.
					'errors' => [['message' => $this->l10n->t('You are not allowed to create signature requests')]],
				]),
				Http::STATUS_UNPROCESSABLE_ENTITY,
			);
		}
	}

	public function iRequestedSignThisFile(IUser $user, int $fileId): void {
		$libresignFile = $this->fileMapper->getById($fileId);
		if ($libresignFile->getUserId() !== $user->getUID()) {
			// TRANSLATORS Permission error when the current user cannot perform the requested LibreSign action.
			throw new LibresignException($this->l10n->t('You do not have permission for this action.'));
		}
	}

	public function validateFileStatus(array $data): void {
		if (array_key_exists('status', $data)) {
			$validStatusList = [
				FileStatus::DRAFT->value,
				FileStatus::ABLE_TO_SIGN->value,
				FileStatus::DELETED->value
			];
			if (!in_array($data['status'], $validStatusList)) {
				// TRANSLATORS Validation error when an unsupported LibreSign file status code is provided.
				throw new LibresignException($this->l10n->t('Invalid status code for file.'));
			}
			if (!empty($data['uuid'])) {
				$file = $this->fileMapper->getByUuid($data['uuid']);
			} elseif (!empty($data['file']['fileId'])) {
				try {
					$file = $this->fileMapper->getById($data['file']['fileId']);
				} catch (\Throwable) {
				}
			}
			if (isset($file)) {
				if ($data['status'] > $file->getStatus()) {
					if ($file->getStatus() >= FileStatus::ABLE_TO_SIGN->value) {
						if ($data['status'] !== FileStatus::DELETED->value) {
							// TRANSLATORS Validation error when changing a document status after signers have already been invited or signing has started.
							throw new LibresignException($this->l10n->t('Sign process already started. Unable to change status.'));
						}
					}
				}
			} elseif ($data['status'] === FileStatus::DELETED->value) {
				// TRANSLATORS Validation error when an unsupported LibreSign file status code is provided.
				throw new LibresignException($this->l10n->t('Invalid status code for file.'));
			}
		}
	}

	public function validateIdentifySigners(array $data): void {
		if (empty($data['signers'])) {
			return;
		}

		$this->validateSignersDataStructure($data);
		$this->validateSigningParticipantsRequired($data);
		$this->docMdpValidator->validateSignersCount($data);
		$this->validateDocMdpPdfRestrictions($data);

		foreach ($data['signers'] as $signer) {
			$this->validateSignerData($signer);
		}
	}

	private function validateSigningParticipantsRequired(array $data): void {
		if (($data['status'] ?? FileStatus::DRAFT->value) === FileStatus::DRAFT->value) {
			return;
		}

		if (!is_array($data['signers'])) {
			return;
		}

		foreach ($data['signers'] as $signer) {
			if (!is_array($signer)) {
				continue;
			}

			$role = ParticipantRole::fromNullable($signer['participantRole'] ?? null);
			if ($role->canSign()) {
				return;
			}
		}

		// TRANSLATORS Validation error when requesting signatures without any signing participants.
		throw new LibresignException($this->l10n->t('At least one signer is required'));
	}

	private function validateSignersDataStructure(array $data): void {
		if (empty($data) || !array_key_exists('signers', $data) || !is_array($data['signers']) || empty($data['signers'])) {
			// TRANSLATORS Validation error when a signature request is submitted without any signers.
			throw new LibresignException($this->l10n->t('No signers'));
		}
	}

	private function validateSignerData(mixed $signer): void {
		if (!is_array($signer) || empty($signer)) {
			// TRANSLATORS Validation error when a signature request is submitted without any signers.
			throw new LibresignException($this->l10n->t('No signers'));
		}

		$this->validateSignerDisplayName($signer);
		$this->validateSignerIdentifyMethods($signer);
		$this->validateParticipantRole($signer);
	}

	private function validateParticipantRole(array $signer): void {
		$roleValue = $signer['participantRole'] ?? ParticipantRole::SIGNER->value;
		if (!is_string($roleValue)) {
			throw new LibresignException('Invalid participant role');
		}

		try {
			$role = ParticipantRole::from($roleValue);
		} catch (\ValueError) {
			throw new LibresignException('Invalid participant role');
		}

		if ($role === ParticipantRole::OBSERVER
			&& !$this->policyService->resolve(ObserverProfilePolicy::KEY)->getEffectiveValueAsBool(false)
		) {
			// TRANSLATORS Validation error when observer participants are submitted while the feature is disabled by policy.
			throw new LibresignException($this->l10n->t('Observer participants are not enabled'));
		}
	}

	private function validateSignerDisplayName(array $signer): void {
		if (isset($signer['displayName']) && strlen($signer['displayName']) > 64) {
			// It's an api error, don't translate
			throw new LibresignException('Display name must not be longer than 64 characters');
		}
	}

	private function validateSignerIdentifyMethods(array $signer): void {
		$normalizedMethods = $this->normalizeSignerIdentifyMethods($signer);

		foreach ($normalizedMethods as $method) {
			$this->validateIdentifyMethodForRequest($method['name'], $method['value']);
		}
	}

	/**
	 * @return list<array{name: string, value: string}>
	 */
	public function normalizeSignerIdentifyMethods(array $signer): array {
		if (empty($signer['identifyMethods']) || !is_array($signer['identifyMethods'])) {
			throw new LibresignException('No identify methods for signer');
		}

		$normalizedMethods = [];

		foreach ($signer['identifyMethods'] as $data) {
			$normalizedMethods[] = $this->normalizeIdentifyMethodsStructure($data);
		}
		return $normalizedMethods;
	}

	/**
	 * @param list<array<string, mixed>> $signers
	 * @return list<array<string, mixed>>
	 */
	public function normalizeRequestSigners(array $signers): array {
		$normalizedSigners = [];

		foreach ($signers as $signer) {
			if (!is_array($signer)) {
				// TRANSLATORS Validation error when a signature request is submitted without any signers.
				throw new LibresignException($this->l10n->t('No signers'));
			}

			$normalizedMethods = array_map(
				fn (array $method): array => [
					'method' => $method['name'],
					'value' => $method['value'],
				],
				$this->normalizeSignerIdentifyMethods($signer),
			);

			$normalizedSigners[] = [
				...$signer,
				'identifyMethods' => $normalizedMethods,
			];
		}

		return $normalizedSigners;
	}

	private function normalizeIdentifyMethodsStructure(mixed $data): array {
		if (!is_array($data) || !array_key_exists('method', $data) || !array_key_exists('value', $data)) {
			throw new LibresignException('Invalid identify method structure');
		}

		return [
			'name' => $data['method'],
			'value' => $data['value'],
		];
	}

	private function validateIdentifyMethodForRequest(string $name, string $identifyValue): void {
		$identifyMethod = $this->identifyMethodService->getInstanceOfIdentifyMethod($name, $identifyValue);
		$identifyMethod->validateToRequest();

		$signatureMethods = $identifyMethod->getSignatureMethods();
		if (empty($signatureMethods)) {
			// It's an api error, don't translate
			throw new LibresignException('No signature methods for identify method ' . $name);
		}
	}

	public function validateExistingFile(array $data): void {
		if (isset($data['uuid'])) {
			$this->validateFileUuid($data);
			$file = $this->fileMapper->getByUuid($data['uuid']);
			$this->iRequestedSignThisFile($data['userManager'], $file->getId());
		} elseif (isset($data['file'])) {
			if (!isset($data['file']['fileId'])) {
				// TRANSLATORS Validation error when the provided Nextcloud fileID is invalid for LibreSign.
				throw new LibresignException($this->l10n->t('Invalid fileID'));
			}
			$this->validateLibreSignFileId($data['file']['fileId']);
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
			// TRANSLATORS Validation error when signer user data is missing from the signature request payload.
			throw new LibresignException($this->l10n->t('No user data'));
		}
		if (empty($data['email'])) {
			if (!empty($data['uid'])) {
				$user = $this->userManager->get($data['uid']);
				if (!$user) {
					// TRANSLATORS Validation error when the Nextcloud user associated with a signer cannot be found.
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

	public function validateFileUuid(array $data): void {
		try {
			$this->fileMapper->getByUuid($data['uuid']);
		} catch (\Throwable) {
			// TRANSLATORS Validation error when a file UUID used in a signature request is invalid.
			throw new LibresignException($this->l10n->t('Invalid UUID file'));
		}
	}

	public function validateSigner(string $uuid, ?IUser $user = null): void {
		$this->validateSignerUuidExists($uuid);
		$this->validateSignerStatus($uuid);
		$this->validateIdentifyMethod($uuid, $user);
	}

	public function validateSignerUuid(string $uuid): void {
		$this->validateSignerUuidExists($uuid);
	}

	/**
	 * @throws LibresignException
	 */
	private function validateSignerStatus(string $uuid): void {
		$signRequest = $this->signRequestMapper->getByUuid($uuid);

		if (!$signRequest->getParticipantRoleEnum()->canSign()) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				// TRANSLATORS Validation error when an observer tries to sign a document.
				'errors' => [['message' => $this->l10n->t('Observers cannot sign this document')]],
			]));
		}

		$status = $signRequest->getStatusEnum();

		$file = $this->fileMapper->getById($signRequest->getFileId());
		$this->sequentialSigningService->setFile($file);

		if ($status === \OCA\Libresign\Enum\SignRequestStatus::DRAFT) {
			try {
				$idDocs = $this->idDocsMapper->getByFileId($signRequest->getFileId());
				if (!empty($idDocs)) {
					return;
				}
			} catch (\Throwable) {
			}

			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				// TRANSLATORS Validation error when the signer tries to sign before they are allowed to (for example, earlier steps are incomplete).
				'errors' => [['message' => $this->l10n->t('You are not allowed to sign this document yet')]],
			]));
		}

		if ($status === \OCA\Libresign\Enum\SignRequestStatus::SIGNED) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				// TRANSLATORS Validation error when the signer tries to sign a document that is already fully signed.
				'errors' => [['message' => $this->l10n->t('Document already signed')]],
			]));
		}

		if (
			$this->sequentialSigningService->isOrderedNumericFlow()
			&& $this->sequentialSigningService->hasPendingLowerOrderSigners(
				$signRequest->getFileId(),
				$signRequest->getSigningOrder()
			)
		) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				// TRANSLATORS Validation error when the signer tries to sign before they are allowed to (for example, earlier steps are incomplete).
				'errors' => [['message' => $this->l10n->t('You are not allowed to sign this document yet')]],
			]));
		}
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
				// TRANSLATORS Validation error when a UUID used to identify a signature request or document is invalid.
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
				// TRANSLATORS Validation error when a UUID used to identify a signature request or document is invalid.
				'errors' => [['message' => $this->l10n->t('Invalid UUID')]],
			]), Http::STATUS_NOT_FOUND);
		}
	}

	public function validateIsSignerOfFile(int $signRequestId, int $fileId): void {
		try {
			$this->signRequestMapper->getByFileIdAndSignRequestId($fileId, $signRequestId);
		} catch (\Throwable) {
			// TRANSLATORS Validation error when the person is not listed as a signer on this document.
			throw new LibresignException($this->l10n->t('Signer not associated to this file'));
		}
	}

	public function validateUserHasNoFileWithThisType(string $uid, string $type): void {
		$exists = $this->idDocsMapper->getByUserAndType($uid, $type);
		if ($exists !== null) {
			// TRANSLATORS Validation error when another file of the same LibreSign type (for example an identity document) is already linked.
			throw new LibresignException($this->l10n->t('A file of this type has been associated.'));
		}
	}

	public function canSignWithIdentificationDocumentStatus(?IUser $user, int $status): void {
		if ($user && $this->userCanApproveValidationDocuments($user, false)) {
			return;
		}

		$allowedStatus = [
			FileService::IDENTIFICATION_DOCUMENTS_DISABLED,
			FileService::IDENTIFICATION_DOCUMENTS_APPROVED,
		];
		if (!in_array($status, $allowedStatus)) {
			throw new LibresignException(
				// TRANSLATORS Validation error when signing is blocked until the signer has an approved identity document.
				$this->l10n->t('You need to have an approved identification document to sign.'),
				JSActions::ACTION_SIGN_ID_DOC,
			);
		}
	}

	public function validateCredentials(SignRequest $signRequest, string $identifyMethodName, string $identifyValue, string $token): void {
		if ($identifyMethodName === IdentifyMethodService::IDENTIFY_PASSWORD && $token === '') {
			// TRANSLATORS Validation error when the password required to unlock the signing certificate is incorrect.
			throw new LibresignException($this->l10n->t('libresign', 'Invalid password'));
		}
		$this->validateIfIdentifyMethodExists($identifyMethodName);
		if ($signRequest->getSigned()) {
			// TRANSLATORS Validation error when the document was already signed and cannot be signed again.
			throw new LibresignException($this->l10n->t('File already signed.'));
		}
		$identifyMethod = $this->resolveIdentifyMethod($signRequest, $identifyMethodName, $identifyValue);
		$identifyMethod->setCodeSentByUser($token);
		$identifyMethod->validateToSign();
		$this->runtimeRequirementValidator->validate($signRequest);
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

		// TRANSLATORS Validation error when the chosen method to identify the signer (account, email, SMS, etc.) is invalid.
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
		// TRANSLATORS Validation error when the chosen method to identify the signer (account, email, SMS, etc.) is invalid.
		throw new LibresignException($this->l10n->t('Invalid identification method'));
	}

	public function validateIfIdentifyMethodExists(string $identifyMethod): void {
		if (!$this->identifyMethodService->exists($identifyMethod)) {
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
			// TRANSLATORS Validation error when the LibreSign file type is not accepted for this action.
			throw new LibresignException($this->l10n->t('Invalid file type.'));
		}
	}

	public function userCanApproveValidationDocuments(?IUser $user, bool $throw = true): bool {
		return $this->idDocsPolicyService->userCanApproveValidationDocuments($user, $throw);
	}

	private function validateDocMdpPdfRestrictions(array $data): void {
		if (empty($data['uuid']) || empty($data['signers'])) {
			return;
		}

		try {
			$file = $this->fileMapper->getByUuid($data['uuid']);
			$this->docMdpValidator->validatePdfRestrictions($file);
		} catch (DoesNotExistException) {
		}
	}
}
