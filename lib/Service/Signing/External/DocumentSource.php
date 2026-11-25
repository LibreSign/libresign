<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\External;

use OCA\Libresign\Db\File as LibreSignFile;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Service\FolderService;
use OCP\Files\File as NextcloudFile;

/** Read the authoritative current PDF revision from LibreSign-owned storage. */
final class DocumentSource {
	private const MAX_INPUT_BYTES = 25 * 1024 * 1024;

	public function __construct(
		private FileMapper $files,
		private FolderService $folders,
	) {
	}

	public function get(LibreSignFile $file): DocumentRevision {
		$sourceNodeId = $file->getSignedNodeId() ?: $file->getNodeId();
		if ($sourceNodeId === null || $sourceNodeId < 1) {
			throw new \DomainException('Authoritative source PDF is unavailable');
		}
		$uid = $this->files->getStorageUserIdByUuid($file->getUuid());
		$node = $this->folders->getReadableNodeById($uid, $sourceNodeId);
		if (!$node instanceof NextcloudFile) {
			throw new \DomainException('Authoritative source document cannot be read');
		}
		$pdf = $node->getContent();
		$revision = $node->getEtag();
		if ($revision === '' || strlen($revision) > 128 || strlen($pdf) > self::MAX_INPUT_BYTES
			|| !str_starts_with($pdf, '%PDF-')) {
			throw new \DomainException('Authoritative PDF source or revision is invalid');
		}
		return new DocumentRevision($node, $pdf, $revision);
	}
}
