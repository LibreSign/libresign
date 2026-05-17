<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicy;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicyValue;

class TsaValidationService {
	public function __construct(
		private PolicyService $policyService,
	) {
	}

	/**
	 * Validate TSA configuration
	 *
	 * @throws LibresignException if TSA is misconfigured
	 */
	public function validateConfiguration(): void {
		$tsaUrl = $this->getTsaUrl();
		if (empty($tsaUrl)) {
			return;
		}

		$this->validateTsaUrlFormat($tsaUrl);
		$this->validateTsaHostResolution($tsaUrl);
	}

	private function getTsaUrl(): string {
		$rawPolicyValue = $this->policyService->resolve(TsaPolicy::KEY)->getEffectiveValue();
		$decoded = TsaPolicyValue::decode($rawPolicyValue);
		return $decoded['url'];
	}

	private function validateTsaUrlFormat(string $tsaUrl): void {
		$tsaUrlParsed = parse_url($tsaUrl);

		$scheme = $tsaUrlParsed['scheme'] ?? '';
		if ($scheme !== '' && !in_array(strtolower($scheme), ['http', 'https'], true)) {
			throw new LibresignException('TSA URL must use HTTP or HTTPS protocol: ' . $tsaUrl);
		}

		if (!filter_var($tsaUrl, FILTER_VALIDATE_URL)) {
			throw new LibresignException('Invalid TSA URL format: ' . $tsaUrl);
		}

		if (!isset($tsaUrlParsed['host'])) {
			throw new LibresignException('Invalid TSA URL: ' . $tsaUrl);
		}
	}

	private function validateTsaHostResolution(string $tsaUrl): void {
		$host = (string)parse_url($tsaUrl, PHP_URL_HOST);

		if (filter_var($host, FILTER_VALIDATE_IP)) {
			return;
		}

		if (!@gethostbyname($host) || gethostbyname($host) === $host) {
			throw new LibresignException('Timestamp Authority (TSA) service is unavailable. Check DNS/network/firewall connectivity from this server: ' . $tsaUrl);
		}
	}
}
