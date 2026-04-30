<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignatureBackground;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicySpec;

final class SignatureBackgroundPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'signature_background_type';
	public const SYSTEM_APP_CONFIG_KEY = 'signature_background_type';

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
				defaultSystemValue: 'default',
				allowedValues: [
					'default',
					'custom',
					'deleted',
				],
				normalizer: static fn (mixed $rawValue): string => self::normalizeBackgroundType($rawValue),
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY,
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . $this->normalizePolicyKey($policyKey)),
		};
	}

	private static function normalizeBackgroundType(mixed $rawValue): string {
		if (!is_string($rawValue)) {
			return 'default';
		}

		$normalized = trim(strtolower($rawValue));
		if (in_array($normalized, ['default', 'custom', 'deleted'], true)) {
			return $normalized;
		}

		return 'default';
	}

	private function normalizePolicyKey(string|\BackedEnum $policyKey): string {
		if ($policyKey instanceof \BackedEnum) {
			return (string)$policyKey->value;
		}

		return $policyKey;
	}
}
