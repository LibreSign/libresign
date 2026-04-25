<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Crl;

final class CrlDistributionPointsExtractor {
	/** @var list<string> */
	private const ACCEPTED_EXTENSION_NAMES = [
		'crldistributionpoints',
		'x509v3 crl distribution points',
		'2.5.29.31',
	];

	/**
	 * @param array<array-key, mixed> $extensions
	 * @return array{hasExtension: bool, urls: list<string>}
	 */
	public function extractFromExtensions(array $extensions): array {
		$values = [];
		foreach ($extensions as $extensionName => $extensionValue) {
			if (!is_string($extensionName)) {
				continue;
			}

			$normalizedName = strtolower(trim($extensionName));
			if (!in_array($normalizedName, self::ACCEPTED_EXTENSION_NAMES, true)) {
				continue;
			}

			if (is_string($extensionValue)) {
				$values[] = $extensionValue;
			} elseif (is_array($extensionValue)) {
				$values[] = implode("\n", array_filter($extensionValue, 'is_string'));
			}
		}

		if (empty($values)) {
			return ['hasExtension' => false, 'urls' => []];
		}

		$urls = [];
		foreach ($values as $value) {
			preg_match_all('/URI\s*:\s*([^\s\n]+)/i', $value, $matches);
			if (!empty($matches[1])) {
				$urls = [...$urls, ...$matches[1]];
			}
		}

		/** @var list<string> $uniqueUrls */
		$uniqueUrls = array_values(array_unique($urls));

		return [
			'hasExtension' => true,
			'urls' => $uniqueUrls,
		];
	}
}