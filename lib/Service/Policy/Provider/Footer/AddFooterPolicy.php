<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\Footer;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicySpec;

final class AddFooterPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'add_footer';
	public const SYSTEM_APP_CONFIG_KEY = 'add_footer';

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
				defaultSystemValue: true,
				allowedValues: [
					true,
					false,
				],
				normalizer: static function (mixed $rawValue): mixed {
					if (is_bool($rawValue)) {
						return $rawValue;
					}

					if (is_int($rawValue)) {
						return $rawValue === 1;
					}

					if (is_string($rawValue)) {
						return in_array(strtolower($rawValue), ['1', 'true', 'yes', 'on'], true);
					}

					return (bool)$rawValue;
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
