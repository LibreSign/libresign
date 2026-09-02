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
	 * @return array{mode: string}
	 */
	public static function normalize(mixed $rawValue): array {
		if (is_string($rawValue)) {
			$decoded = json_decode($rawValue, true);
			if (is_array($decoded)) {
				$rawValue = $decoded;
			}
		}

		if (!is_array($rawValue)) {
			return self::defaults();
		}

		$mode = SignerGeolocationMode::tryFrom((string)($rawValue['mode'] ?? ''));
		if ($mode === null) {
			$mode = SignerGeolocationMode::DISABLED;
		}

		return [
			'mode' => $mode->value,
		];
	}

	/**
	 * @return array{mode: string}
	 */
	public static function defaults(): array {
		return [
			'mode' => SignerGeolocationMode::DISABLED->value,
		];
	}

	public static function getMode(mixed $rawValue): SignerGeolocationMode {
		$normalized = self::normalize($rawValue);
		return SignerGeolocationMode::from($normalized['mode']);
	}

	public static function isEnabled(mixed $rawValue): bool {
		return self::getMode($rawValue) !== SignerGeolocationMode::DISABLED;
	}
}
