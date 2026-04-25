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
		$orderedUrls = [];
		$seenUrls = [];
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
				$this->appendUrlsFromText($extensionValue, $orderedUrls, $seenUrls);
			} elseif (is_array($extensionValue)) {
				foreach ($extensionValue as $extensionPart) {
					if (is_string($extensionPart)) {
						$this->appendUrlsFromText($extensionPart, $orderedUrls, $seenUrls);
					}
				}
			}
		}

		if (!$hasCrlExtension) {
			return ['hasExtension' => false, 'urls' => []];
		}

		return [
			'hasExtension' => true,
			'urls' => $orderedUrls,
		];
	}

	/**
	 * @param list<string> $orderedUrls
	 * @param array<string, true> $seenUrls
	 */
	private function appendUrlsFromText(string $value, array &$orderedUrls, array &$seenUrls): void {
		if (stripos($value, 'URI') === false) {
			return;
		}

		preg_match_all(self::URI_PATTERN, $value, $matches);
		if (empty($matches[1])) {
			return;
		}

		foreach ($matches[1] as $url) {
			$normalizedUrl = $this->normalizeUrlToken($url);
			if ($normalizedUrl === '' || isset($seenUrls[$normalizedUrl])) {
				continue;
			}

			$seenUrls[$normalizedUrl] = true;
			$orderedUrls[] = $normalizedUrl;
		}
	}

	private function normalizeUrlToken(string $url): string {
		return rtrim($url, ")]");
	}
}