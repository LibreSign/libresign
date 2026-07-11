<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Doctrine\DBAL\Types {
	class JsonType {
		public function getSQLDeclaration(array $column, \Doctrine\DBAL\Platforms\AbstractPlatform $platform) {
			return '';
		}
	}
}
