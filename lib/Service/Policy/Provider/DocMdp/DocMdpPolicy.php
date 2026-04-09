<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\DocMdp;

use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicySpec;

final class DocMdpPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'docmdp';
	public const SYSTEM_APP_CONFIG_KEY = 'docmdp_level';

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
				defaultSystemValue: DocMdpLevel::NOT_CERTIFIED->value,
				allowedValues: [
					DocMdpLevel::NOT_CERTIFIED->value,
					DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED->value,
					DocMdpLevel::CERTIFIED_FORM_FILLING->value,
					DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS->value,
				],
				normalizer: static function (mixed $rawValue): mixed {
					if ($rawValue instanceof DocMdpLevel) {
						return $rawValue->value;
					}

					if (is_int($rawValue)) {
						return $rawValue;
					}

					return $rawValue;
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
