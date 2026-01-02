<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Envelope;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FolderService;
use OCP\Files\Node;
use OCP\IUser;

class EnvelopeFileRelocator {
	public function __construct(
		private FolderService $folderService,
	) {
	}

	public function ensureFileInEnvelopeFolder(Node $sourceNode, int $envelopeFolderId, IUser $userManager): Node {
		$this->folderService->setUserId($userManager->getUID());
		$userRootFolder = $this->folderService->getUserRootFolder();
		$envelopeFolder = $userRootFolder->getFirstNodeById($envelopeFolderId);

		if (!$envelopeFolder instanceof \OCP\Files\Folder) {
			throw new LibresignException('Envelope folder not found');
		}

		if ($this->isNodeInsideFolder($sourceNode, $envelopeFolder)) {
			return $sourceNode;
		}

		if (!$sourceNode instanceof \OCP\Files\File) {
			throw new LibresignException('Invalid file type for envelope');
		}

		return $envelopeFolder->newFile($sourceNode->getName(), $sourceNode->getContent());
	}

	private function isNodeInsideFolder(Node $node, \OCP\Files\Folder $folder): bool {
		return str_starts_with($node->getPath(), $folder->getPath() . '/');
	}
}
