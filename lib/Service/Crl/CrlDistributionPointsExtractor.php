<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Crl;

final class CrlDistributionPointsExtractor {
	/** @var array<string, true> */
	private const ACCEPTED_EXTENSION_NAMES = [
		'crldistributionpoints' => true,
		'x509v3 crl distribution points' => true,
		'2.5.29.31' => true,
	];

	private const URI_PATTERN = '/URI\s*:\s*([^\s\n]+)/i';

	/**
	 * @param array<array-key, mixed> $extensions
	 * @return array{hasExtension: bool, urls: list<string>}
	 */
	public function extractFromExtensions(array $extensions): array {
		$hasCrlExtension = false;
		$urls = [];
		foreach ($extensions as $extensionName => $extensionValue) {
			if (!is_string($extensionName)) {
				continue;
			}

			$normalizedName = strtolower(trim($extensionName));
			if (!isset(self::ACCEPTED_EXTENSION_NAMES[$normalizedName])) {
				continue;
			}
			$hasCrlExtension = true;

			if (is_string($extensionValue)) {
				$this->appendUrlsFromText($extensionValue, $urls);
			} elseif (is_array($extensionValue)) {
				foreach ($extensionValue as $extensionPart) {
					if (is_string($extensionPart)) {
						$this->appendUrlsFromText($extensionPart, $urls);
					}
				}
			}
		}

		if (!$hasCrlExtension) {
			return ['hasExtension' => false, 'urls' => []];
		}

		/** @var list<string> $uniqueUrls */
		$uniqueUrls = array_values(array_unique($urls));

		return [
			'hasExtension' => true,
			'urls' => $uniqueUrls,
		];
	}

	/**
	 * @param list<string> $urls
	 */
	private function appendUrlsFromText(string $value, array &$urls): void {
		preg_match_all(self::URI_PATTERN, $value, $matches);
		if (empty($matches[1])) {
			return;
		}

		foreach ($matches[1] as $url) {
			$normalizedUrl = $this->normalizeUrlToken($url);
			if ($normalizedUrl !== '') {
				$urls[] = $normalizedUrl;
			}
		}
	}

	private function normalizeUrlToken(string $url): string {
		return rtrim($url, ")]");
	}
}