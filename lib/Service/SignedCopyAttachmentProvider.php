<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCP\Files\File;

/**
 * Reads the signed PDFs of a signed copy mail when it is delivered, so the
 * queue never stores document content.
 */
class SignedCopyAttachmentProvider {
	/**
	 * Raw size of the attachments in one mail. Base64 grows it by about a
	 * third, which keeps the message under the common 25 MB SMTP limit.
	 */
	public const MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024;

	public function __construct(
		private FileMapper $fileMapper,
		private FolderService $folderService,
	) {
	}

	/**
	 * Returns every requested document of the request when all of them are
	 * readable and fit in one mail. Otherwise returns nothing and the mail
	 * links to the validation page instead.
	 *
	 * @param list<int> $documentIds
	 * @return array<string, string> file name => content
	 */
	public function load(FileEntity $request, array $documentIds): array {
		$request = $this->fileMapper->getById($request->getId());
		$documents = $request->isEnvelope()
			? $this->fileMapper->getChildrenFiles($request->getId())
			: [$request];

		$nodes = [];
		$size = 0;
		foreach ($documents as $document) {
			if (!in_array($document->getId(), $documentIds, true)) {
				continue;
			}
			$node = $this->getSignedNode($document);
			if ($node === null) {
				return [];
			}
			$size += $node->getSize();
			$nodes[] = $node;
		}
		if ($nodes === [] || count($nodes) !== count($documentIds) || $size > self::MAX_ATTACHMENT_BYTES) {
			return [];
		}

		$attachments = [];
		foreach ($nodes as $node) {
			$attachments[$this->uniqueName($node->getName(), $attachments)] = $node->getContent();
		}
		return $attachments;
	}

	private function getSignedNode(FileEntity $document): ?File {
		$nodeId = $document->getSignedNodeId();
		if ($nodeId === null) {
			return null;
		}
		try {
			$node = $this->folderService->getReadableNodeById($document->getUserId(), $nodeId);
		} catch (\Exception) {
			$node = null;
		}
		return $node instanceof File ? $node : null;
	}

	/** @param array<string, string> $attachments */
	private function uniqueName(string $name, array $attachments): string {
		$candidate = $name;
		$counter = 1;
		while (isset($attachments[$candidate])) {
			$counter++;
			$extension = pathinfo($name, PATHINFO_EXTENSION);
			$base = $extension === '' ? $name : substr($name, 0, -strlen($extension) - 1);
			$candidate = $base . ' (' . $counter . ')' . ($extension === '' ? '' : '.' . $extension);
		}
		return $candidate;
	}
}
