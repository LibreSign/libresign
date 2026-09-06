<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\Tsa;

final class TsaPolicyValue {
	public const string DEFAULT_HASH_ALGORITHM = 'SHA256';
	/**
	 * Algorithms offered for the timestamp query. SHA1 is left out on purpose:
	 * authorities reject it, which is what issue #8145 reports.
	 *
	 * @var string[]
	 */
	public const array HASH_ALGORITHMS = ['SHA256', 'SHA384', 'SHA512'];

	/** @return array{url: string, policy_oid: string, auth_type: string, username: string, hash_algorithm: string} */
	public static function defaults(): array {
		return [
			'url' => '',
			'policy_oid' => '',
			'auth_type' => 'none',
			'username' => '',
			'hash_algorithm' => self::DEFAULT_HASH_ALGORITHM,
		];
	}

	/** @return array{url: string, policy_oid: string, auth_type: string, username: string, hash_algorithm: string} */
	public static function decode(mixed $rawValue): array {
		if (is_string($rawValue)) {
			$decoded = json_decode($rawValue, true);
			if (is_array($decoded)) {
				$rawValue = $decoded;
			}
		}

		if (!is_array($rawValue)) {
			return self::defaults();
		}

		$url = isset($rawValue['url']) && is_string($rawValue['url'])
			? trim($rawValue['url'])
			: '';

		$policyOid = isset($rawValue['policy_oid']) && is_string($rawValue['policy_oid'])
			? trim($rawValue['policy_oid'])
			: '';

		if ($policyOid !== '' && !preg_match('/^[0-9]+(\.[0-9]+)*$/', $policyOid)) {
			$policyOid = '';
		}

		$authType = isset($rawValue['auth_type']) && is_string($rawValue['auth_type'])
			? trim($rawValue['auth_type'])
			: 'none';
		if (!in_array($authType, ['none', 'basic'], true)) {
			$authType = 'none';
		}

		$username = isset($rawValue['username']) && is_string($rawValue['username'])
			? trim($rawValue['username'])
			: '';

		if ($authType !== 'basic') {
			$username = '';
		}

		$hashAlgorithm = isset($rawValue['hash_algorithm']) && is_string($rawValue['hash_algorithm'])
			? strtoupper(trim($rawValue['hash_algorithm']))
			: self::DEFAULT_HASH_ALGORITHM;
		if (!in_array($hashAlgorithm, self::HASH_ALGORITHMS, true)) {
			$hashAlgorithm = self::DEFAULT_HASH_ALGORITHM;
		}

		return [
			'url' => $url,
			'policy_oid' => $policyOid,
			'auth_type' => $authType,
			'username' => $username,
			'hash_algorithm' => $hashAlgorithm,
		];
	}

	public static function encode(mixed $rawValue): string {
		return json_encode(self::decode($rawValue), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
	}
}
