<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Identify;

use OCP\IConfig;
use OCP\IPhoneNumberUtil;

class SearchNormalizer {
	private const PHONE_BASED_METHODS = ['whatsapp', 'sms', 'telegram', 'signal'];

	public function __construct(
		private IConfig $config,
		private IPhoneNumberUtil $phoneNumberUtil,
	) {
	}

	public function normalize(string $search, string $method): string {
		// Non-phone methods return unchanged
		if (!in_array($method, self::PHONE_BASED_METHODS, true)) {
			return $search;
		}

		// Already in international format
		if (str_starts_with($search, '+')) {
			return $search;
		}

		$defaultRegion = $this->config->getSystemValueString('default_phone_region', '');
		if ($defaultRegion === '') {
			return $search;
		}

		// convertToStandardFormat validates and normalizes, returns null if invalid
		$standardFormat = $this->phoneNumberUtil->convertToStandardFormat($search, $defaultRegion);

		return $standardFormat ?? $search;
	}
}
