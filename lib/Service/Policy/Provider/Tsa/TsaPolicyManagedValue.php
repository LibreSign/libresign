<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\Tsa;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Settings\Admin;
use OCP\IAppConfig;

final class TsaPolicyManagedValue {
	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function normalizeForPersistence(mixed $rawValue): string {
		$value = is_array($rawValue) || is_string($rawValue)
			? $rawValue
			: [];

		$rawPayload = $this->decodeRawPayload($value);
		$rawPolicyOid = isset($rawPayload['policy_oid']) && is_string($rawPayload['policy_oid'])
			? trim($rawPayload['policy_oid'])
			: '';
		if ($rawPolicyOid !== '' && !preg_match('/^[0-9]+(\.[0-9]+)*$/', $rawPolicyOid)) {
			throw new \InvalidArgumentException('Invalid OID format');
		}

		$normalized = TsaPolicyValue::decode($value);
		if ($normalized['url'] === '') {
			$this->clearStoredPassword();
			return TsaPolicyValue::encode(TsaPolicyValue::defaults());
		}

		$urlScheme = parse_url($normalized['url'], PHP_URL_SCHEME);
		if (!filter_var($normalized['url'], FILTER_VALIDATE_URL)
			|| !is_string($urlScheme)
			|| !in_array($urlScheme, ['http', 'https'], true)) {
			throw new \InvalidArgumentException('Invalid URL format');
		}

		if ($normalized['auth_type'] !== 'basic') {
			$this->clearStoredPassword();
			$normalized['username'] = '';
			return TsaPolicyValue::encode($normalized);
		}

		$password = isset($rawPayload['password']) && is_string($rawPayload['password'])
			? trim($rawPayload['password'])
			: '';
		$hasFreshPassword = $password !== '' && $password !== Admin::PASSWORD_PLACEHOLDER;
		$existingPassword = $this->appConfig->getValueString(Application::APP_ID, TsaPolicy::PASSWORD_APP_CONFIG_KEY, '');
		$hasPersistedPassword = $existingPassword !== '';

		if ($normalized['username'] === '' && !$hasFreshPassword && !$hasPersistedPassword) {
			throw new \InvalidArgumentException('Username and password are required for basic authentication');
		}

		if ($normalized['username'] === '') {
			throw new \InvalidArgumentException('Username is required');
		}

		if (!$hasFreshPassword && !$hasPersistedPassword) {
			throw new \InvalidArgumentException('Password is required');
		}

		if ($hasFreshPassword) {
			$this->appConfig->setValueString(
				Application::APP_ID,
				key: TsaPolicy::PASSWORD_APP_CONFIG_KEY,
				value: $password,
				sensitive: true,
			);
		}

		return TsaPolicyValue::encode($normalized);
	}

	private function clearStoredPassword(): void {
		if ($this->appConfig->hasKey(Application::APP_ID, TsaPolicy::PASSWORD_APP_CONFIG_KEY)) {
			$this->appConfig->deleteKey(Application::APP_ID, TsaPolicy::PASSWORD_APP_CONFIG_KEY);
		}
	}

	/** @return array<string, mixed> */
	private function decodeRawPayload(array|string $rawValue): array {
		if (is_array($rawValue)) {
			return $rawValue;
		}

		$decoded = json_decode($rawValue, true);
		return is_array($decoded) ? $decoded : [];
	}
}
