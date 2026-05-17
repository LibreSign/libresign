<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignatureHashAlgorithm;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicySpec;

final class SignatureHashAlgorithmPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'signature_hash_algorithm';
	public const SYSTEM_APP_CONFIG_KEY = 'signature_hash_algorithm';

	/** @var string[] */
	private const ALGORITHMS = [
		'SHA1',
		'SHA256',
		'SHA384',
		'SHA512',
		'RIPEMD160',
	];

	#[\Override]
	public function keys(): array {
		return [
			self::KEY,
		];
	}

	#[\Override]
	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		return match ($this->normalizePolicyKey($policyKey)) {
			self::KEY => new PolicySpec(
				key: self::KEY,
				defaultSystemValue: 'SHA256',
				allowedValues: self::ALGORITHMS,
				normalizer: function (mixed $rawValue): string {
					$candidate = strtoupper(trim((string)$rawValue));
					return in_array($candidate, self::ALGORITHMS, true) ? $candidate : 'SHA256';
				},
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY,
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . $this->normalizePolicyKey($policyKey)),
		};
	}

	private function normalizePolicyKey(string|\BackedEnum $policyKey): string {
		if ($policyKey instanceof \BackedEnum) {
			return (string)$policyKey->value;
		}

		return $policyKey;
	}
}
