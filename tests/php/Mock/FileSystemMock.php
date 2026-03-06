<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Mock;

class FileSystemMock {
	public static array $files = [];

	public static function fileExists(string $filename): bool {
		return self::$files[$filename] ?? false;
	}
}
