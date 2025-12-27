<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use OCA\Libresign\Db\File;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;
use stdClass;

class MetadataLoader {
	public function __construct(
		private IRootFolder $root,
		private IMimeTypeDetector $mimeTypeDetector,
		private IURLGenerator $urlGenerator,
		private FileContentProvider $contentProvider,
		private LoggerInterface $logger,
	) {
	}

	public function loadMetadata(?File $file, stdClass $fileData): void {
		if (!$file) {
			return;
		}

		try {
			$fileNode = $this->getFileNode($file);

			if (method_exists($fileNode, 'getSize')) {
				$fileData->size = $fileNode->getSize();
			}

			if (method_exists($fileNode, 'getMimeType')) {
				$fileData->mime = $fileNode->getMimeType();
			} else {
				$content = $this->contentProvider->getContentFromLibresignFile($file);
				$fileData->mime = $this->mimeTypeDetector->detectString($content);
			}

			$fileData->pages = $this->getPages($file);
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to load file metadata: ' . $e->getMessage());
		}
	}

	/**
	 * Get file node from File entity
	 *
	 * @throws \OCA\Libresign\Exception\LibresignException
	 */
	private function getFileNode(File $file): \OCP\Files\File {
		$nodeId = $file->getSignedNodeId();
		if (!$nodeId) {
			$nodeId = $file->getNodeId();
		}

		$fileNode = $this->root->getUserFolder($file->getUserId())->getFirstNodeById($nodeId);

		if (!$fileNode instanceof \OCP\Files\File) {
			throw new \OCA\Libresign\Exception\LibresignException('File not found', 404);
		}

		return $fileNode;
	}

	/**
	 * Get pages array with URLs and resolutions
	 *
	 * @return array<int, array{url: string, resolution: mixed}>
	 */
	private function getPages(File $file): array {
		$return = [];

		$metadata = $file->getMetadata();
		$pageCount = $metadata['p'] ?? 0;

		for ($page = 1; $page <= $pageCount; $page++) {
			$return[] = [
				'url' => $this->urlGenerator->linkToRoute('ocs.libresign.File.getPage', [
					'apiVersion' => 'v1',
					'uuid' => $file->getUuid(),
					'page' => $page,
				]),
				'resolution' => $metadata['d'][$page - 1] ?? null,
			];
		}

		return $return;
	}
}
