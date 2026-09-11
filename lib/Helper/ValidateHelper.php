<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Helper;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCA\Libresign\Service\Validation\SignerValidator;
use OCA\Libresign\Service\Validation\SigningRequestValidator;
use OCA\Libresign\Service\Validation\VisibleElementValidator;
use OCP\IUser;

/**
 * Backward-compatible validation facade.
 *
 * Validation rules live in cohesive validators under Service\Validation.
 * Consumers can migrate to those services directly without changing behavior.
 */
class ValidateHelper {
	public const TYPE_TO_SIGN = FileInputValidator::TYPE_TO_SIGN;
	public const TYPE_VISIBLE_ELEMENT_PDF = FileInputValidator::TYPE_VISIBLE_ELEMENT_PDF;
	public const TYPE_VISIBLE_ELEMENT_USER = FileInputValidator::TYPE_VISIBLE_ELEMENT_USER;
	public const TYPE_ACCOUNT_DOCUMENT = FileInputValidator::TYPE_ACCOUNT_DOCUMENT;
	public const VALID_MIMETIPE = FileInputValidator::VALID_MIMETIPE;

	public function __construct(
		private FileInputValidator $fileInputValidator,
		private VisibleElementValidator $visibleElementValidator,
		private SigningRequestValidator $signingRequestValidator,
		private SignerValidator $signerValidator,
		private IdentityDocumentValidator $identityDocumentValidator,
	) {
	}

	public function validateNewFile(array $data, int $type = self::TYPE_TO_SIGN, ?IUser $user = null): void {
		$this->fileInputValidator->validateNewFile($data, $type, $user);
	}

	public function validateFile(array $data, int $type = self::TYPE_TO_SIGN, ?IUser $user = null): void {
		$this->fileInputValidator->validateFile($data, $type, $user);
	}

	public function validateBase64(string $base64, int $type = self::TYPE_TO_SIGN): void {
		$this->fileInputValidator->validateBase64($base64, $type);
	}

	public function validateNotRequestedSign(int $nodeId): void {
		$this->fileInputValidator->validateNotRequestedSign($nodeId);
	}

	public function validateIfNodeIdExists(int $nodeId, string $userId = '', int $type = self::TYPE_TO_SIGN): void {
		$this->fileInputValidator->validateIfNodeIdExists($nodeId, $userId, $type);
	}

	public function validateMimeTypeAcceptedByNodeId(int $nodeId, string $userId = '', int $type = self::TYPE_TO_SIGN): void {
		$this->fileInputValidator->validateMimeTypeAcceptedByNodeId($nodeId, $userId, $type);
	}

	public function validateMimeTypeAcceptedByMime(string $mimetype, int $type = self::TYPE_TO_SIGN): void {
		$this->fileInputValidator->validateMimeTypeAcceptedByMime($mimetype, $type);
	}

	public function validateLibreSignFileId(int $fileId): void {
		$this->fileInputValidator->validateLibreSignFileId($fileId);
	}

	public function validateVisibleElements(?array $visibleElements, int $type): void {
		$this->visibleElementValidator->validateVisibleElements($visibleElements, $type);
	}

	public function validateVisibleElement(array $element, int $type): void {
		$this->visibleElementValidator->validateVisibleElement($element, $type);
	}

	public function validateElementSignRequestId(array $element, int $type): void {
		$this->visibleElementValidator->validateElementSignRequestId($element, $type);
	}

	public function validateElementCoordinates(array $element): void {
		$this->visibleElementValidator->validateElementCoordinates($element);
	}

	public function validateElementPage(array $element): void {
		$this->visibleElementValidator->validateElementPage($element);
	}

	public function validateElementType(array $element): void {
		$this->visibleElementValidator->validateElementType($element);
	}

	public function validateVisibleElementsRelation(array $list, SignRequest $signRequest, ?IUser $user): void {
		$this->visibleElementValidator->validateVisibleElementsRelation($list, $signRequest, $user);
	}

	public function validateAuthenticatedUserIsOwnerOfPdfVisibleElement(int $documentElementId, string $uid): void {
		$this->visibleElementValidator->validateAuthenticatedUserIsOwnerOfPdfVisibleElement($documentElementId, $uid);
	}

	public function validateWorkflowIsNotClosedByFileId(int $fileId): void {
		$this->signingRequestValidator->validateWorkflowIsNotClosedByFileId($fileId);
	}

	public function validateWorkflowIsNotClosedByUuid(string $uuid): void {
		$this->signingRequestValidator->validateWorkflowIsNotClosedByUuid($uuid);
	}

	public function validateWorkflowIsNotClosed(File $file): void {
		$this->signingRequestValidator->validateWorkflowIsNotClosed($file);
	}

	public function fileCanBeSigned(File $file): void {
		$this->signingRequestValidator->fileCanBeSigned($file);
	}

	public function canRequestSign(IUser $user): void {
		$this->signingRequestValidator->canRequestSign($user);
	}

	public function iRequestedSignThisFile(IUser $user, int $fileId): void {
		$this->signingRequestValidator->iRequestedSignThisFile($user, $fileId);
	}

	public function validateFileStatus(array $data): void {
		$this->signingRequestValidator->validateFileStatus($data);
	}

	public function validateExistingFile(array $data): void {
		$this->signingRequestValidator->validateExistingFile($data);
	}

	public function validateFileUuid(array $data): void {
		$this->signingRequestValidator->validateFileUuid($data);
	}

	public function validateIsSignerOfFile(int $signRequestId, int $fileId): void {
		$this->signingRequestValidator->validateIsSignerOfFile($signRequestId, $fileId);
	}

	public function validateIdentifySigners(array $data): void {
		$this->signerValidator->validateIdentifySigners($data);
	}

	public function normalizeSignerIdentifyMethods(array $signer): array {
		return $this->signerValidator->normalizeSignerIdentifyMethods($signer);
	}

	public function normalizeRequestSigners(array $signers): array {
		return $this->signerValidator->normalizeRequestSigners($signers);
	}

	public function validateSigner(string $uuid, ?IUser $user = null): void {
		$this->signerValidator->validateSigner($uuid, $user);
	}

	public function validateSignerUuid(string $uuid): void {
		$this->signerValidator->validateSignerUuid($uuid);
	}

	public function validateRenewSigner(string $uuid, ?IUser $user = null): void {
		$this->signerValidator->validateRenewSigner($uuid, $user);
	}

	public function validateUuidFormat(string $uuid): void {
		$this->signerValidator->validateUuidFormat($uuid);
	}

	public function validateCredentials(SignRequest $signRequest, string $identifyMethodName, string $identifyValue, string $token): void {
		$this->signerValidator->validateCredentials($signRequest, $identifyMethodName, $identifyValue, $token);
	}

	public function validateIfIdentifyMethodExists(string $identifyMethod): void {
		$this->signerValidator->validateIfIdentifyMethodExists($identifyMethod);
	}

	public function validateIdDocIsOwnedByUser(int $nodeId, string $uid): void {
		$this->identityDocumentValidator->validateIdDocIsOwnedByUser($nodeId, $uid);
	}

	public function validateIdDocBelongsToSignRequest(int $nodeId, int $signRequestId): void {
		$this->identityDocumentValidator->validateIdDocBelongsToSignRequest($nodeId, $signRequestId);
	}

	public function validateUserHasNoFileWithThisType(string $uid, string $type): void {
		$this->identityDocumentValidator->validateUserHasNoFileWithThisType($uid, $type);
	}

	public function canSignWithIdentificationDocumentStatus(?IUser $user, int $status): void {
		$this->identityDocumentValidator->canSignWithIdentificationDocumentStatus($user, $status);
	}

	public function validateFileTypeExists(string $type): void {
		$this->identityDocumentValidator->validateFileTypeExists($type);
	}

	public function userCanApproveValidationDocuments(?IUser $user, bool $throw = true): bool {
		return $this->identityDocumentValidator->userCanApproveValidationDocuments($user, $throw);
	}

	public function haveValidMail(array $data, ?int $type = null): void {
		$this->identityDocumentValidator->haveValidMail($data, $type);
	}
}
