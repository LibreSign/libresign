<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCP\IUser;
use OCP\IUserSession;

class FileAccessService {
	public function __construct(
		private FileMapper $fileMapper,
		private SignFileService $signFileService,
		private IUserSession $userSession,
	) {
	}

	public function userCanAccessFileById(int $fileId, ?IUser $user = null): bool {
		$user = $this->resolveUser($user);
		if (!$user) {
			return false;
		}

		return $this->userCanAccessFile($this->fileMapper->getById($fileId), $user);
	}

	public function userCanAccessFileByNodeId(int $nodeId, ?IUser $user = null): bool {
		$user = $this->resolveUser($user);
		if (!$user) {
			return false;
		}

		return $this->userCanAccessFile($this->fileMapper->getByNodeId($nodeId), $user);
	}

	private function resolveUser(?IUser $user): ?IUser {
		return $user ?? $this->userSession->getUser();
	}

	private function userCanAccessFile(FileEntity $file, IUser $user): bool {
		if ($file->getUserId() === $user->getUID()) {
			return true;
		}

		return $this->userCanSignFile($file, $user);
	}

	private function userCanSignFile(FileEntity $file, IUser $user): bool {
		try {
			$this->signFileService->getSignRequestToSign($file, null, $user);
			return true;
		} catch (\Exception) {
			return false;
		}
	}
}
