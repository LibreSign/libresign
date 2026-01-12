<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCP\IAppConfig;

class TsaValidationService {
	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * Validate TSA configuration
	 *
	 * @throws LibresignException if TSA is misconfigured
	 */
	public function validateConfiguration(): void {
		$tsaUrl = $this->appConfig->getValueString(Application::APP_ID, 'tsa_url', '');
		if (empty($tsaUrl)) {
			// TSA not configured, nothing to validate
			return;
		}

		// Basic URL validation
		if (!filter_var($tsaUrl, FILTER_VALIDATE_URL)) {
			throw new LibresignException('Invalid TSA URL format: ' . $tsaUrl);
		}

		// Check if URL is reachable
		$tsaUrlParsed = parse_url($tsaUrl);
		if (!isset($tsaUrlParsed['host'])) {
			throw new LibresignException('Invalid TSA URL: ' . $tsaUrl);
		}

		// Try to resolve hostname to detect DNS/connectivity issues early
		$host = (string)$tsaUrlParsed['host'];
		if (!@gethostbyname($host) || gethostbyname($host) === $host) {
			// Could not resolve hostname
			throw new LibresignException('Timestamp Authority (TSA) service is unavailable or misconfigured: ' . $tsaUrl);
		}
	}
}
