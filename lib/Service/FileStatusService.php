<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;

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
		}
		return $file;
	}

	public function canNotifySigners(?int $fileStatus): bool {
		return $fileStatus === FileEntity::STATUS_ABLE_TO_SIGN;
	}
}
