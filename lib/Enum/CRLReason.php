<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

/**
 * RFC 5280 CRLReason codes
 */
enum CRLReason: int {
	case UNSPECIFIED = 0;
	case KEY_COMPROMISE = 1;
	case CA_COMPROMISE = 2;
	case AFFILIATION_CHANGED = 3;
	case SUPERSEDED = 4;
	case CESSATION_OF_OPERATION = 5;
	case CERTIFICATE_HOLD = 6;
	case REMOVE_FROM_CRL = 8;
	case PRIVILEGE_WITHDRAWN = 9;
	case AA_COMPROMISE = 10;

	public function getDescription(): string {
		return match ($this) {
			self::UNSPECIFIED => 'unspecified',
			self::KEY_COMPROMISE => 'keyCompromise',
			self::CA_COMPROMISE => 'cACompromise',
			self::AFFILIATION_CHANGED => 'affiliationChanged',
			self::SUPERSEDED => 'superseded',
			self::CESSATION_OF_OPERATION => 'cessationOfOperation',
			self::CERTIFICATE_HOLD => 'certificateHold',
			self::REMOVE_FROM_CRL => 'removeFromCRL',
			self::PRIVILEGE_WITHDRAWN => 'privilegeWithdrawn',
			self::AA_COMPROMISE => 'aACompromise',
		};
	}

	public static function isValid(int $code): bool {
		return self::tryFrom($code) !== null;
	}
}
