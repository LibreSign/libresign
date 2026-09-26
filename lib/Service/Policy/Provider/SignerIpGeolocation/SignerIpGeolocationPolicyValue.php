<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignerIpGeolocation;

use OCA\Libresign\Enum\SignerIpGeolocationMode;

final class SignerIpGeolocationPolicyValue {
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

		$mode = SignerIpGeolocationMode::tryFrom((string)($rawValue['mode'] ?? ''));
		if ($mode === null) {
			$mode = SignerIpGeolocationMode::DISABLED;
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
			'mode' => SignerIpGeolocationMode::DISABLED->value,
		];
	}

	public static function getMode(mixed $rawValue): SignerIpGeolocationMode {
		$normalized = self::normalize($rawValue);
		return SignerIpGeolocationMode::from($normalized['mode']);
	}

	public static function isEnabled(mixed $rawValue): bool {
		return self::getMode($rawValue) === SignerIpGeolocationMode::ENABLED;
	}
}
