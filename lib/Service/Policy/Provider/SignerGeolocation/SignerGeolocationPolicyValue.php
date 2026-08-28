<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignerGeolocation;

use OCA\Libresign\Enum\SignerGeolocationMode;

final class SignerGeolocationPolicyValue {
	/**
	 * @return array{mode: string, allowRequesterOverride: bool}
	 */
	public static function normalize(mixed $rawValue): array {
		if (!is_array($rawValue)) {
			return self::defaults();
		}

		$mode = SignerGeolocationMode::tryFrom((string)($rawValue['mode'] ?? ''));
		if ($mode === null) {
			$mode = SignerGeolocationMode::DISABLED;
		}

		return [
			'mode' => $mode->value,
			'allowRequesterOverride' => filter_var(
				$rawValue['allowRequesterOverride'] ?? false,
				FILTER_VALIDATE_BOOLEAN,
				FILTER_NULL_ON_FAILURE,
			) ?? false,
		];
	}

	/**
	 * @return array{mode: string, allowRequesterOverride: bool}
	 */
	public static function defaults(): array {
		return [
			'mode' => SignerGeolocationMode::DISABLED->value,
			'allowRequesterOverride' => false,
		];
	}

	public static function getMode(mixed $rawValue): SignerGeolocationMode {
		$normalized = self::normalize($rawValue);
		return SignerGeolocationMode::from($normalized['mode']);
	}

	public static function isAllowRequesterOverride(mixed $rawValue): bool {
		return self::normalize($rawValue)['allowRequesterOverride'];
	}

	public static function isEnabled(mixed $rawValue): bool {
		return self::getMode($rawValue) !== SignerGeolocationMode::DISABLED;
	}
}
