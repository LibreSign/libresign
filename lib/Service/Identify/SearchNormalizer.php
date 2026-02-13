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
		if (!in_array($method, self::PHONE_BASED_METHODS, true)) {
			return $search;
		}

		if (str_starts_with($search, '+')) {
			return $search;
		}

		$defaultRegion = $this->config->getSystemValueString('default_phone_region', '');
		if ($defaultRegion === '') {
			return $search;
		}

		$standardFormat = $this->phoneNumberUtil->convertToStandardFormat($search, $defaultRegion);

		return $standardFormat ?? $search;
	}

	public function tryNormalizePhoneNumber(string $phoneNumber, string $method): ?string {
		if (!in_array($method, self::PHONE_BASED_METHODS, true)) {
			return null;
		}

		$phoneNumber = trim($phoneNumber);
		if ($phoneNumber === '') {
			return null;
		}

		if (str_starts_with($phoneNumber, '+')) {
			return $phoneNumber;
		}

		$defaultRegion = $this->config->getSystemValueString('default_phone_region', '');
		if ($defaultRegion === '') {
			return null;
		}

		return $this->phoneNumberUtil->convertToStandardFormat($phoneNumber, $defaultRegion);
	}
}
