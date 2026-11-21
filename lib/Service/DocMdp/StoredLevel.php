<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\DocMdp;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Enum\FileStatus;

/**
 * A small compatibility snapshot for stable33/34, which predate request policies.
 * Level 0 in legacy rows without provenance is ambiguous and keeps the old
 * global fallback; newly saved level 0 is explicitly not certified.
 */
final class StoredLevel {
	private const string CAPTURED = 'docmdp_level_captured';
	private const string FROZEN = 'docmdp_level_frozen';

	public static function isSignableStatus(int $status): bool {
		return in_array($status, [
			FileStatus::ABLE_TO_SIGN->value,
			FileStatus::PARTIAL_SIGNED->value,
			FileStatus::SIGNED->value,
			FileStatus::SIGNING_IN_PROGRESS->value,
		], true);
	}

	public static function isFrozen(FileEntity $file): bool {
		return (($file->getMetadata() ?? [])[self::FROZEN] ?? false) === true
			|| self::isSignableStatus($file->getStatus());
	}

	public static function initialize(FileEntity $file, DocMdpLevel $level): void {
		self::persist($file, $level, self::isSignableStatus($file->getStatus()));
	}

	public static function freeze(FileEntity $file, DocMdpLevel $level): void {
		if (self::isFrozen($file)) {
			return;
		}

		self::persist($file, $level, true);
	}

	public static function forSigning(FileEntity $file): ?DocMdpLevel {
		if (!self::isFrozen($file)) {
			return null;
		}

		$level = (int)$file->getDocmdpLevel();
		$metadata = $file->getMetadata() ?? [];
		if ($level === 0 && ($metadata[self::CAPTURED] ?? false) !== true) {
			return null;
		}

		return DocMdpLevel::tryFrom($level);
	}

	private static function persist(FileEntity $file, DocMdpLevel $level, bool $freeze): void {
		$metadata = $file->getMetadata() ?? [];
		$metadata[self::CAPTURED] = true;
		if ($freeze) {
			$metadata[self::FROZEN] = true;
		}
		$file->setDocmdpLevelEnum($level);
		$file->setMetadata($metadata);
	}
}
