<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCP\IUser;
use OCP\IUserSession;

class FileAccessService {
	public function __construct(
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
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

		if ($this->userIsAssociatedSigner($file, $user)) {
			return true;
		}

		return $this->userCanSignFile($file, $user);
	}

	private function userIsAssociatedSigner(FileEntity $file, IUser $user): bool {
		if ($file->getStatus() === FileStatus::DRAFT->value) {
			return false;
		}

		$signRequests = array_values(array_filter(
			$this->signRequestMapper->getByFileId($file->getId()),
			static fn (SignRequest $signRequest): bool => $signRequest->getStatus() !== SignRequestStatus::DRAFT->value,
		));
		if ($signRequests === []) {
			return false;
		}

		$identifyMethods = $this->signRequestMapper->getIdentifyMethodsFromSigners($signRequests);
		foreach ($signRequests as $signRequest) {
			if ($this->signRequestMatchesUser($identifyMethods[$signRequest->getId()] ?? [], $user)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<int|string, IdentifyMethod> $identifyMethods
	 */
	private function signRequestMatchesUser(array $identifyMethods, IUser $user): bool {
		return array_reduce($identifyMethods, function (bool $carry, IdentifyMethod $identifyMethod) use ($user): bool {
			if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_ACCOUNT) {
				return $user->getUID() === $identifyMethod->getIdentifierValue();
			}
			if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL && $user->getEMailAddress()) {
				return $user->getEMailAddress() === $identifyMethod->getIdentifierValue();
			}
			return $carry;
		}, false);
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
