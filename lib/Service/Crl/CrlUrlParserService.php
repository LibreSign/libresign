<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Crl;

use OCP\IURLGenerator;

class CrlUrlParserService {
	public function __construct(
		private IURLGenerator $urlGenerator,
	) {
	}

	public function parseUrl(string $crlUrl): ?array {
		$path = parse_url($crlUrl, PHP_URL_PATH);
		if (!is_string($path)) {
			return null;
		}

		$pattern = '#^/(?:index\.php/)?apps/libresign/crl/libresign_([A-Za-z0-9]+)_(\d+)_([a-z])\.crl$#';
		if (!preg_match($pattern, $path, $matches)) {
			return null;
		}

		return [
			'instanceId' => $matches[1],
			'generation' => (int)$matches[2],
			'engineType' => $matches[3],
		];
	}

}
