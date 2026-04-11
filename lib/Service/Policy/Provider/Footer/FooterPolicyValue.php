<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\Footer;

final class FooterPolicyValue {
	/** @return array{enabled: bool, writeQrcodeOnFooter: bool, validationSite: string, customizeFooterTemplate: bool} */
	public static function defaults(): array {
		return [
			'enabled' => true,
			'writeQrcodeOnFooter' => true,
			'validationSite' => '',
			'customizeFooterTemplate' => false,
		];
	}

	/** @return array{enabled: bool, writeQrcodeOnFooter: bool, validationSite: string, customizeFooterTemplate: bool} */
	public static function normalize(mixed $rawValue): array {
		$defaults = self::defaults();

		if (is_array($rawValue)) {
			return [
				'enabled' => self::toBool($rawValue['enabled'] ?? $rawValue['addFooter'] ?? $defaults['enabled']),
				'writeQrcodeOnFooter' => self::toBool($rawValue['writeQrcodeOnFooter'] ?? $rawValue['write_qrcode_on_footer'] ?? $defaults['writeQrcodeOnFooter']),
				'validationSite' => self::toString($rawValue['validationSite'] ?? $rawValue['validation_site'] ?? $defaults['validationSite']),
				'customizeFooterTemplate' => self::toBool($rawValue['customizeFooterTemplate'] ?? $rawValue['customize_footer_template'] ?? $defaults['customizeFooterTemplate']),
			];
		}

		if (is_bool($rawValue) || is_int($rawValue)) {
			$defaults['enabled'] = self::toBool($rawValue);
			return $defaults;
		}

		if (is_string($rawValue)) {
			$trimmedValue = trim($rawValue);
			if ($trimmedValue === '') {
				return $defaults;
			}

			$decoded = json_decode($trimmedValue, true);
			if (is_array($decoded)) {
				return self::normalize($decoded);
			}

			$defaults['enabled'] = self::toBool($trimmedValue);
			return $defaults;
		}

		return $defaults;
	}

	public static function encode(array $value): string {
		return (string)json_encode(self::normalize($value), JSON_UNESCAPED_SLASHES);
	}

	public static function isEnabled(mixed $rawValue): bool {
		return self::normalize($rawValue)['enabled'];
	}

	public static function isQrCodeEnabled(mixed $rawValue): bool {
		$normalized = self::normalize($rawValue);
		return $normalized['enabled'] && $normalized['writeQrcodeOnFooter'];
	}

	private static function toBool(mixed $rawValue): bool {
		if (is_bool($rawValue)) {
			return $rawValue;
		}

		if (is_int($rawValue)) {
			return $rawValue === 1;
		}

		if (is_string($rawValue)) {
			return in_array(strtolower(trim($rawValue)), ['1', 'true', 'yes', 'on'], true);
		}

		return (bool)$rawValue;
	}

	private static function toString(mixed $rawValue): string {
		if (!is_scalar($rawValue)) {
			return '';
		}

		return trim((string)$rawValue);
	}
}
