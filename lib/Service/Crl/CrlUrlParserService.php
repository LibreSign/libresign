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
		$templateUrl = $this->urlGenerator->linkToRouteAbsolute('libresign.crl.getRevocationList', [
			'instanceId' => 'INSTANCEID',
			'generation' => 999999,
			'engineType' => 'ENGINETYPE',
		]);

		$patternUrl = str_replace('INSTANCEID', '([a-z0-9]+)', $templateUrl);
		$patternUrl = str_replace('999999', '(\d+)', $patternUrl);
		$patternUrl = str_replace('ENGINETYPE', '([a-z])', $patternUrl);
		$escapedPattern = str_replace([':', '/', '.'], ['\:', '\/', '\.'], $patternUrl);
		$escapedPattern = str_replace('\/index\.php', '', $escapedPattern);
		$escapedPattern = str_replace('\/apps\/', '(?:\/index\.php)?\/apps\/', $escapedPattern);
		$pattern = '/^' . $escapedPattern . '$/i';

		if (!preg_match($pattern, $crlUrl, $matches)) {
			return null;
		}

		return [
			'instanceId' => $matches[1],
			'generation' => (int)$matches[2],
			'engineType' => $matches[3],
		];
	}

}
