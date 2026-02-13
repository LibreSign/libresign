<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

enum CertificateEngineType: string {
	case OpenSSL = 'o';
	case CFSSL = 'c';

	public function getEngineName(): string {
		return match($this) {
			self::OpenSSL => 'openssl',
			self::CFSSL => 'cfssl',
		};
	}

	public static function tryFromValue(mixed $value): ?self {
		return match($value) {
			'o', 'openssl' => self::OpenSSL,
			'c', 'cfssl' => self::CFSSL,
			default => null,
		};
	}
}
