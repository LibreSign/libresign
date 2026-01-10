<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
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
		return $fileStatus === FileStatus::ABLE_TO_SIGN->value;
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

		$minStatus = FileStatus::SIGNED->value;
		$maxStatus = FileStatus::DRAFT->value;

		foreach ($children as $child) {
			$status = $child->getStatus();
			if ($status < $minStatus) {
				$minStatus = $status;
			}
			if ($status > $maxStatus) {
				$maxStatus = $status;
			}
		}

		$newStatus = FileStatus::DRAFT->value;

		if ($minStatus === FileStatus::SIGNED->value && $maxStatus === FileStatus::SIGNED->value) {
			$newStatus = FileStatus::SIGNED->value;
		} elseif ($maxStatus >= FileStatus::PARTIAL_SIGNED->value) {
			$newStatus = FileStatus::PARTIAL_SIGNED->value;
		} elseif ($maxStatus >= FileStatus::ABLE_TO_SIGN->value) {
			$newStatus = FileStatus::ABLE_TO_SIGN->value;
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

	public function propagateStatusToChildren(int $envelopeId, int $newStatus): void {
		try {
			$envelope = $this->fileMapper->getById($envelopeId);
		} catch (DoesNotExistException) {
			return;
		}

		if (!$envelope->isEnvelope()) {
			return;
		}

		$children = $this->fileMapper->getChildrenFiles($envelopeId);

		foreach ($children as $child) {
			if ($child->getStatus() !== $newStatus) {
				$child->setStatus($newStatus);
				$this->fileMapper->update($child);
			}
		}
	}
}
