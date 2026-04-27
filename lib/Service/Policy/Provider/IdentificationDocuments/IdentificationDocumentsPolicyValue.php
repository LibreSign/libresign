<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\IdentificationDocuments;

final class IdentificationDocumentsPolicyValue {
	/** @var list<string> */
	private const TRUE_VALUES = ['1', 'true'];

	/** @var list<string> */
	private const FALSE_VALUES = ['0', 'false', ''];

	public static function normalize(mixed $rawValue, bool $default = false): bool {
		if (is_bool($rawValue)) {
			return $rawValue;
		}

		if (is_int($rawValue)) {
			if ($rawValue === 1) {
				return true;
			}

			if ($rawValue === 0) {
				return false;
			}

			return $default;
		}

		if (is_string($rawValue)) {
			$value = strtolower(trim($rawValue));
			if (in_array($value, self::TRUE_VALUES, true)) {
				return true;
			}

			if (in_array($value, self::FALSE_VALUES, true)) {
				return false;
			}

			return $default;
		}

		return $default;
	}
}