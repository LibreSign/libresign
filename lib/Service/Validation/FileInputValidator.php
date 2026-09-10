<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Validation;

use InvalidArgumentException;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FolderService;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\IUser;
use OCP\User\Exceptions\UserNotFoundException;

class FileInputValidator {
	public const TYPE_TO_SIGN = 1;
	public const TYPE_VISIBLE_ELEMENT_PDF = 2;
	public const TYPE_VISIBLE_ELEMENT_USER = 3;
	public const TYPE_ACCOUNT_DOCUMENT = 4;

	public function __construct(
		private IL10N $l10n,
		private SignRequestMapper $signRequestMapper,
		private FileMapper $fileMapper,
		private IMimeTypeDetector $mimeTypeDetector,
		private FolderService $folderService,
	) {
	}

	public function validateNewFile(array $data, int $type = self::TYPE_TO_SIGN, ?IUser $user = null): void {
		$this->validateFile($data, $type, $user);
		if (!empty($data['file']['nodeId'])) {
			$this->validateNotRequestedSign((int)$data['file']['nodeId']);
		} elseif (!empty($data['file']['path'])) {
			$uid = $this->resolveUserId($data, $user);
			$node = $this->folderService->getFileByPath($data['file']['path'], $uid);
			$this->validateNotRequestedSign($node->getId());
		}
	}

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
				throw new LibresignException($this->l10n->t('File type: %s. Empty file.', [$this->getTypeOfFile($type)]));
			}
			if ($type === self::TYPE_VISIBLE_ELEMENT_USER && $this->elementNeedFile($data)) {
				throw new LibresignException($this->l10n->t('Elements of type %s need file.', [$data['type']]));
			}
			return;
		}

		if (!empty($data['file']['url'])) {
			if (!filter_var($data['file']['url'], FILTER_VALIDATE_URL)) {
				throw new LibresignException($this->l10n->t('File type: %s. Specify a URL, a Base64 string or a fileID.', [$this->getTypeOfFile($type)]));
			}
			return;
		}

		if (!empty($data['file']['nodeId'])) {
			if (!is_numeric($data['file']['nodeId'])) {
				throw new LibresignException($this->l10n->t('File type: %s. Invalid fileID.', [$this->getTypeOfFile($type)]));
			}
			$uid = $this->resolveUserId($data, $user);
			$this->validateIfNodeIdExists((int)$data['file']['nodeId'], $uid, $type);
			$this->validateMimeTypeAcceptedByNodeId((int)$data['file']['nodeId'], $uid, $type);
			return;
		}

		if (!empty($data['file']['fileId']) && $type === self::TYPE_VISIBLE_ELEMENT_PDF) {
			if (!is_numeric($data['file']['fileId'])) {
				throw new LibresignException($this->l10n->t('File type: %s. Invalid fileID.', [$this->getTypeOfFile($type)]));
			}
			$this->validateLibreSignFileId((int)$data['file']['fileId']);
			return;
		}

		if (!empty($data['file']['base64'])) {
			$this->validateBase64($data['file']['base64'], $type);
			return;
		}

		if (!empty($data['file']['path'])) {
			$uid = $this->resolveUserId($data, $user);
			$this->folderService->getFileByPath($data['file']['path'], $uid);
			return;
		}

		throw new LibresignException($this->l10n->t('File type: %s. Specify a URL, Base64 string, path or a fileID.', [$this->getTypeOfFile($type)]));
	}

	public function validateBase64(string $base64, int $type = self::TYPE_TO_SIGN): void {
		$withMime = explode(',', $base64);
		if (count($withMime) === 2) {
			$withMime[0] = explode(';', $withMime[0]);
			if (count($withMime[0]) !== 2 || $withMime[0][1] !== 'base64') {
				$this->throwInvalidBase64($type);
			}
			if ($type === self::TYPE_TO_SIGN && $withMime[0][0] !== 'data:application/pdf') {
				$this->throwInvalidBase64($type);
			}
			$base64 = $withMime[1];
		}

		$string = base64_decode($base64);
		if (in_array($type, [self::TYPE_VISIBLE_ELEMENT_USER, self::TYPE_VISIBLE_ELEMENT_PDF], true) && strlen($string) > 5000 * 1024) {
			throw new InvalidArgumentException($this->l10n->t('File is too big'));
		}
		if (base64_encode($string) !== $base64) {
			$this->throwInvalidBase64($type);
		}

		$mimeType = $this->mimeTypeDetector->detectString($string);
		if ($type === self::TYPE_TO_SIGN && $mimeType !== 'application/pdf') {
			$this->throwInvalidBase64($type);
		}
		if (in_array($type, [self::TYPE_VISIBLE_ELEMENT_USER, self::TYPE_VISIBLE_ELEMENT_PDF], true) && $mimeType !== 'image/png') {
			$this->throwInvalidBase64($type);
		}
	}

	public function validateNotRequestedSign(int $nodeId): void {
		try {
			$signRequest = $this->signRequestMapper->getByNodeId($nodeId);
		} catch (\Throwable) {
			$signRequest = null;
		}
		if ($signRequest !== null) {
			throw new LibresignException($this->l10n->t('Already asked to sign this document'));
		}
	}

	public function validateIfNodeIdExists(int $nodeId, string $userId = '', int $type = self::TYPE_TO_SIGN): void {
		$userId = $this->resolveStoredFileUserId($nodeId, $userId);
		try {
			$node = $this->folderService->getReadableNodeById($userId, $nodeId);
		} catch (UserNotFoundException) {
			throw new LibresignException($this->l10n->t('User not found.'));
		} catch (NotPermittedException) {
			throw new LibresignException($this->l10n->t('You do not have permission for this action.'));
		}
		if ($node === null) {
			throw new LibresignException($this->l10n->t('File type: %s. Invalid fileID.', [$this->getTypeOfFile($type)]));
		}
	}

	public function validateMimeTypeAcceptedByNodeId(int $nodeId, string $userId = '', int $type = self::TYPE_TO_SIGN): void {
		$userId = $this->resolveStoredFileUserId($nodeId, $userId);
		$file = $this->folderService->getReadableNodeById($userId, $nodeId);
		if ($file === null) {
			throw new LibresignException($this->l10n->t('File type: %s. Invalid fileID.', [$this->getTypeOfFile($type)]));
		}
		$this->validateMimeTypeAcceptedByMime($file->getMimeType(), $type);
	}

	public function validateMimeTypeAcceptedByMime(string $mimetype, int $type = self::TYPE_TO_SIGN): void {
		if ($type === self::TYPE_TO_SIGN && $mimetype !== 'application/pdf') {
			throw new LibresignException($this->l10n->t('File type: %s. Must be a fileID of %s format.', [$this->getTypeOfFile($type), 'PDF']));
		}
		if (in_array($type, [self::TYPE_VISIBLE_ELEMENT_PDF, self::TYPE_VISIBLE_ELEMENT_USER], true) && $mimetype !== 'image/png') {
			throw new LibresignException($this->l10n->t('File type: %s. Must be a fileID of %s format.', [$this->getTypeOfFile($type), 'png']));
		}
	}

	public function validateLibreSignFileId(int $fileId): void {
		try {
			$this->fileMapper->getById($fileId);
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('Invalid fileID'));
		}
	}

	private function resolveUserId(array $data, ?IUser $user): string {
		if ($user instanceof IUser) {
			return $user->getUID();
		}
		if (isset($data['userManager']) && $data['userManager'] instanceof IUser) {
			return $data['userManager']->getUID();
		}
		throw new LibresignException($this->l10n->t('User not found.'));
	}

	private function resolveStoredFileUserId(int $nodeId, string $userId): string {
		if ($userId !== '') {
			return $userId;
		}
		return $this->fileMapper->getByNodeId($nodeId)->getUserId();
	}

	private function elementNeedFile(array $data): bool {
		return in_array($data['type'], ['signature', 'initial'], true);
	}

	private function getTypeOfFile(int $type): string {
		return $type === self::TYPE_TO_SIGN
			? $this->l10n->t('document to sign')
			: $this->l10n->t('visible signature element');
	}

	private function throwInvalidBase64(int $type): never {
		throw new LibresignException($this->l10n->t('File type: %s. Invalid Base64 file.', [$this->getTypeOfFile($type)]));
	}
}
