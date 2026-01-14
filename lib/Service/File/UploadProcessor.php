<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\FileUploadHelper;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\FolderService;
use OCP\Files\Node;
use OCP\IUser;
use Psr\Log\LoggerInterface;

class UploadProcessor {
	public function __construct(
		private FileUploadHelper $uploadHelper,
		private FolderService $folderService,
		private MimeService $mimeService,
		private PdfValidator $pdfValidator,
		private ValidateHelper $validateHelper,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Get Node from uploaded file data
	 *
	 * @param array $data Must contain 'uploadedFile', 'userManager', 'name', and optionally 'settings'
	 * @return Node The created file node
	 * @throws LibresignException
	 */
	public function getNodeFromUploadedFile(array $data): Node {
		if (!$this->folderService->getUserId()) {
			$this->folderService->setUserId($data['userManager']->getUID());
		}

		$uploadedFile = $data['uploadedFile'];

		$this->uploadHelper->validateUploadedFile($uploadedFile);
		$content = $this->uploadHelper->readUploadedFile($uploadedFile);

		$extension = $this->mimeService->getExtension($content);
		$this->validateFileContent($content, $data['name'], $extension);

		$folderToFile = $this->folderService->getFolderForFile($data, $data['userManager']);
		if (!$folderToFile instanceof \OCP\Files\Folder) {
			throw new LibresignException('Envelope folder not found');
		}

		@unlink($uploadedFile['tmp_name']);

		return $folderToFile->newFile($data['name'] . '.' . $extension, $content);
	}

	/**
	 * Process multiple uploaded files with automatic rollback on error
	 *
	 * @param array $filesArray Normalized array of uploaded files
	 * @param IUser $user User who is uploading
	 * @param array $settings Upload settings
	 * @return list<array{fileNode: Node, name: string}>
	 * @throws LibresignException
	 */
	public function processUploadedFilesWithRollback(array $filesArray, IUser $user, array $settings): array {
		$processedFiles = [];
		$createdNodes = [];
		$shouldRollback = true;

		try {
			foreach ($filesArray as $uploadedFile) {
				$fileName = pathinfo($uploadedFile['name'], PATHINFO_FILENAME);

				$node = $this->getNodeFromUploadedFile([
					'userManager' => $user,
					'name' => $fileName,
					'uploadedFile' => $uploadedFile,
					'settings' => $settings,
				]);

				$createdNodes[] = $node;

				$this->validateHelper->validateNewFile([
					'file' => ['fileId' => $node->getId()],
					'userManager' => $user,
				]);

				$processedFiles[] = [
					'fileNode' => $node,
					'name' => $fileName,
				];
			}

			$shouldRollback = false;
			return $processedFiles;
		} finally {
			if ($shouldRollback) {
				$this->rollbackCreatedNodes($createdNodes);
			}
		}
	}

	/**
	 * Validate file content based on extension
	 *
	 * @param string $content The file content
	 * @param string $fileName File name for error messages
	 * @param string $extension The file extension
	 * @throws LibresignException
	 */
	private function validateFileContent(string $content, string $fileName, string $extension): void {
		if ($extension === 'pdf') {
			$this->pdfValidator->validate($content, $fileName);
		}
	}

	/**
	 * Rollback created nodes on error
	 *
	 * @param Node[] $nodes
	 */
	private function rollbackCreatedNodes(array $nodes): void {
		foreach ($nodes as $node) {
			try {
				$node->delete();
			} catch (\Exception $deleteError) {
				$this->logger->error('Failed to rollback uploaded file', [
					'nodeId' => $node->getId(),
					'error' => $deleteError->getMessage(),
				]);
			}
		}
	}
}
