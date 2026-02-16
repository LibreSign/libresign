<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Exception\LibresignException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUser;

class UuidResolverService {
	public function __construct(
		private SignFileService $signFileService,
		private IdDocsMapper $idDocsMapper,
		private IdDocsPolicyService $idDocsPolicyService,
	) {
	}

	/**
	 * Resolve UUID for a user, supporting both SignRequest and id-doc File
	 *
	 * @param string $uuid The UUID to resolve
	 * @param IUser|null $user The authenticating user
	 * @return array{signRequest: SignRequest|null, file: File, type: string}
	 *                                                                        - type: 'sign_request' or 'id_doc'
	 *
	 * @throws LibresignException If UUID is invalid or user lacks permission
	 */
	public function resolveUuidForUser(string $uuid, ?IUser $user): array {
		try {
			$signRequest = $this->signFileService->getSignRequestByUuid($uuid);
			$file = $this->signFileService->getFile($signRequest->getFileId());

			return [
				'signRequest' => $signRequest,
				'file' => $file,
				'type' => 'sign_request',
			];
		} catch (DoesNotExistException|LibresignException) {
		}

		try {
			$file = $this->signFileService->getFileByUuid($uuid);

			try {
				$this->idDocsMapper->getByFileId($file->getId());
				if (!$user || !$this->idDocsPolicyService->canApproverSignIdDoc($user, $file->getId(), $file->getStatus())) {
					throw new LibresignException('User is not authorized to access this identification document');
				}

				return [
					'signRequest' => null,
					'file' => $file,
					'type' => 'id_doc',
				];
			} catch (DoesNotExistException) {
				throw new LibresignException('File is not an identification document');
			}
		} catch (DoesNotExistException|LibresignException $e) {
			if ($e->getMessage() === 'File is not an identification document'
				|| $e->getMessage() === 'User is not authorized to access this identification document') {
				throw $e;
			}
		}

		throw new LibresignException('Invalid UUID');
	}
}
