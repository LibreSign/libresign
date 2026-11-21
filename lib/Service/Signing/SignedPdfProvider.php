<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FolderService;
use OCP\AppFramework\Http;
use OCP\Files\File;
use OCP\IUser;

class SignedPdfProvider {
	public function __construct(
		private FileMapper $fileMapper,
		private FolderService $folderService,
	) {
	}

	/** @return array{content: string, filename: string, uuid: string} */
	public function getSignedPdf(int $fileId, IUser $user): array {
		$file = $this->fileMapper->getById($fileId);
		if ($file->getUserId() !== $user->getUID()
			|| $file->getStatus() !== FileStatus::SIGNED->value
			|| !$file->getSignedHash()
			|| !$file->getSignedNodeId()) {
			throw new LibresignException('The signing request has not completed.', Http::STATUS_CONFLICT);
		}

		$node = $this->folderService->getReadableNodeById($user->getUID(), $file->getSignedNodeId());
		if (!$node instanceof File || $node->getMimeType() !== 'application/pdf') {
			throw new LibresignException('The signed PDF is not accessible to this account.', Http::STATUS_FORBIDDEN);
		}

		$content = $node->getContent();
		if (!str_starts_with($content, '%PDF-')) {
			throw new LibresignException('The signed PDF could not be read.', Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return ['content' => $content, 'filename' => $node->getName(), 'uuid' => (string)$file->getUuid()];
	}
}
