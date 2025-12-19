<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class FileStatusService {
	public function __construct(
		private FileMapper $fileMapper,
	) {
	}

	public function updateFileStatusIfUpgrade(FileEntity $file, int $newStatus): FileEntity {
		$currentStatus = $file->getStatus();
		if ($newStatus > $currentStatus) {
			$file->setStatus($newStatus);
			$this->fileMapper->update($file);

			if ($file->hasParent()) {
				$this->propagateStatusToParent($file->getParentFileId());
			}
		}
		return $file;
	}

	public function canNotifySigners(?int $fileStatus): bool {
		return $fileStatus === FileEntity::STATUS_ABLE_TO_SIGN;
	}

	public function propagateStatusToParent(int $parentId): void {
		try {
			$parent = $this->fileMapper->getById($parentId);
		} catch (DoesNotExistException) {
			return;
		}

		if (!$parent->isEnvelope()) {
			return;
		}

		$children = $this->fileMapper->getChildrenFiles($parentId);

		if (empty($children)) {
			return;
		}

		$minStatus = FileEntity::STATUS_SIGNED;
		$maxStatus = FileEntity::STATUS_DRAFT;

		foreach ($children as $child) {
			$status = $child->getStatus();
			if ($status < $minStatus) {
				$minStatus = $status;
			}
			if ($status > $maxStatus) {
				$maxStatus = $status;
			}
		}

		$newStatus = FileEntity::STATUS_DRAFT;

		if ($minStatus === FileEntity::STATUS_SIGNED && $maxStatus === FileEntity::STATUS_SIGNED) {
			$newStatus = FileEntity::STATUS_SIGNED;
		} elseif ($maxStatus >= FileEntity::STATUS_PARTIAL_SIGNED) {
			$newStatus = FileEntity::STATUS_PARTIAL_SIGNED;
		} elseif ($maxStatus >= FileEntity::STATUS_ABLE_TO_SIGN) {
			$newStatus = FileEntity::STATUS_ABLE_TO_SIGN;
		}

		if ($parent->getStatus() !== $newStatus) {
			$parent->setStatus($newStatus);
			$this->fileMapper->update($parent);
		}
	}

	public function updateFileStatus(FileEntity $file, int $newStatus): FileEntity {
		$file->setStatus($newStatus);
		$this->fileMapper->update($file);

		if ($file->hasParent()) {
			$this->propagateStatusToParent($file->getParentFileId());
		}

		return $file;
	}
}
