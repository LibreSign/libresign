<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Sabre\DAV {
	final class UUIDUtil {
		public static function getUUID(): string {
			return '00000000-0000-0000-0000-000000000000';
		}

		public static function validateUUID(string $uuid): bool {
			return $uuid !== '';
		}
	}
}
