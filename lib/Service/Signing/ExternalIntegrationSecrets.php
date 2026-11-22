<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Exception\ExternalIntegrationSecretException;
use OCP\Security\ICrypto;

/**
 * Encrypts integration secrets for database storage. AppConfig's sensitivity
 * flag does not apply to custom database tables.
 *
 * Only server-side signing adapters may consume decrypted secrets. Controllers
 * must never return, log or serialize the result of decrypt().
 */
final class ExternalIntegrationSecrets {
	private const MAX_SECRET_JSON_BYTES = 16384;
	public function __construct(
		private ICrypto $crypto,
	) {
	}

	/** @param array<string, string> $secrets */
	public function encrypt(#[\SensitiveParameter] array $secrets): string {
		if ($secrets === []) {
			return '';
		}
		$this->assertSecretShape($secrets);
		$serialized = json_encode($secrets, JSON_THROW_ON_ERROR);
		if (strlen($serialized) > self::MAX_SECRET_JSON_BYTES) {
			throw new \InvalidArgumentException('Secret configuration too large');
		}
		try {
			return $this->crypto->encrypt($serialized);
		} catch (\Exception) {
			// ICrypto does not declare a dedicated encryption exception.
			// Fail closed, without exposing a sensitive message or previous cause.
			throw ExternalIntegrationSecretException::protectionFailed();
		}
	}

	/** @return array<string, string> */
	public function decrypt(#[\SensitiveParameter] string $ciphertext): array {
		if ($ciphertext === '') {
			return [];
		}
		try {
			$decoded = json_decode($this->crypto->decrypt($ciphertext), true, 512, JSON_THROW_ON_ERROR);
			if (!is_array($decoded)) {
				throw new \UnexpectedValueException('Unexpected secret payload');
			}
			$this->assertSecretShape($decoded);
			return $decoded;
		} catch (\Exception) {
			// Includes authentication failures, malformed JSON and invalid secret shape.
			throw ExternalIntegrationSecretException::recoveryFailed();
		}
	}

	/**
	 * Omitted keys preserve existing ciphertext. Explicit null clears a key;
	 * required-field policy remains the declarative driver's responsibility.
	 *
	 * @param array<string, string|null> $changes
	 */
	public function applyChanges(#[\SensitiveParameter] string $ciphertext, #[\SensitiveParameter] array $changes): string {
		if ($changes === []) {
			return $ciphertext;
		}
		// Reject malformed patches before reading any existing credential data.
		foreach ($changes as $key => $value) {
			if (!is_string($key) || $key === '' || strlen($key) > 128) {
				throw new \InvalidArgumentException('Invalid secret field');
			}
			if ($value !== null && (!is_string($value) || $value === '' || strlen($value) > 8192)) {
				throw new \InvalidArgumentException('Invalid secret replacement');
			}
		}

		$secrets = $this->decrypt($ciphertext);
		foreach ($changes as $key => $value) {
			if ($value === null) {
				unset($secrets[$key]);
			} else {
				$secrets[$key] = $value;
			}
		}
		return $this->encrypt($secrets);
	}

	/** @param array<mixed, mixed> $secrets */
	private function assertSecretShape(array $secrets): void {
		foreach ($secrets as $key => $value) {
			if (!is_string($key) || $key === '' || strlen($key) > 128 || !is_string($value) || $value === '' || strlen($value) > 8192) {
				throw new \InvalidArgumentException('Invalid secret data');
			}
		}
	}
}
