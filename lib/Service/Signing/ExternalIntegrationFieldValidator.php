<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

/**
 * Validates administrative input against a driver's declarative fields.
 * No provider-specific field names or credentials are defined here.
 * The outbound HTTP transport must independently enforce DNS, IP and redirect
 * safety at connection time; configuration validation cannot prevent rebinding.
 */
final class ExternalIntegrationFieldValidator {
	/**
	 * @param list<array<string, mixed>> $fields Trusted driver metadata from the catalog.
	 * @param array<mixed, mixed> $configuration Non-secret values, supplied in full.
	 * @param array<mixed, mixed> $secrets New secret values, never public configuration.
	 */
	public function validateCreate(array $fields, #[\SensitiveParameter] array $configuration, #[\SensitiveParameter] array $secrets): void {
		$this->validate($fields, $configuration, $secrets, true);
	}

	/**
	 * @param list<array<string, mixed>> $fields Trusted driver metadata from the catalog.
	 * @param array<mixed, mixed> $configuration Full non-secret configuration after merging the patch.
	 * @param array<mixed, mixed> $secretChanges Omitted keys preserve ciphertext; null explicitly clears an optional secret.
	 */
	public function validateUpdate(array $fields, #[\SensitiveParameter] array $configuration, #[\SensitiveParameter] array $secretChanges): void {
		$this->validate($fields, $configuration, $secretChanges, false);
	}

	/**
	 * @param list<array<string, mixed>> $fields
	 * @param array<mixed, mixed> $configuration
	 * @param array<mixed, mixed> $secrets
	 */
	private function validate(array $fields, #[\SensitiveParameter] array $configuration, #[\SensitiveParameter] array $secrets, bool $creating): void {
		$definitions = [];
		foreach ($fields as $field) {
			$key = $field['key'] ?? null;
			$validation = $field['validation'] ?? [];
			if (!is_string($key) || $key === '' || isset($definitions[$key])
				|| !isset($field['type'], $field['secret'], $field['required'])
				|| !is_bool($field['secret']) || !is_bool($field['required'])
				|| !is_array($validation)
				|| (!$field['secret'] && (($validation['schemes'] ?? null) !== ['https']
					|| ($validation['allowUserInfo'] ?? null) !== false))
				|| ($field['secret'] && $field['type'] !== 'password')
				|| (!$field['secret'] && $field['type'] !== 'url')) {
				throw new \InvalidArgumentException('Invalid integration field definition');
			}
			$definitions[$key] = $field;
		}

		foreach ($configuration as $key => $value) {
			if (!is_string($key) || !isset($definitions[$key]) || $definitions[$key]['secret']) {
				throw new \InvalidArgumentException('Invalid public integration field');
			}
			$this->validateHttpsUrl($value);
		}

		foreach ($secrets as $key => $value) {
			if (!is_string($key) || !isset($definitions[$key]) || !$definitions[$key]['secret']) {
				throw new \InvalidArgumentException('Invalid integration secret field');
			}
			if ($value === null && !$creating && !$definitions[$key]['required']) {
				continue;
			}
			if (!is_string($value) || $value === '' || strlen($value) > 8192) {
				throw new \InvalidArgumentException('Invalid integration secret replacement');
			}
		}

		foreach ($definitions as $key => $field) {
			if (!$field['required']) {
				continue;
			}
			if ($field['secret'] && $creating && !array_key_exists($key, $secrets)) {
				throw new \InvalidArgumentException('Missing required integration secret');
			}
			if (!$field['secret'] && !array_key_exists($key, $configuration)) {
				throw new \InvalidArgumentException('Missing required integration field');
			}
		}
	}

	/** @param mixed $value */
	private function validateHttpsUrl(#[\SensitiveParameter] mixed $value): void {
		if (!is_string($value) || $value === '' || strlen($value) > 2048
			|| preg_match('/[\x00-\x20\x7f]/', $value) === 1
			|| filter_var($value, FILTER_VALIDATE_URL) === false) {
			throw new \InvalidArgumentException('Invalid external signing URL');
		}
		$parts = parse_url($value);
		if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https'
			|| empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
			|| isset($parts['fragment']) || isset($parts['query'])) {
			throw new \InvalidArgumentException('Invalid external signing URL');
		}
		$host = strtolower(rtrim(trim($parts['host'], '[]'), '.'));
		if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false
			|| !str_contains($host, '.') || preg_match('/^[0-9.]+$/D', $host) === 1
			|| filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
			|| str_ends_with($host, '.localhost') || str_ends_with($host, '.local')
			|| str_ends_with($host, '.internal')) {
			throw new \InvalidArgumentException('Unsafe external signing URL');
		}
	}
}
