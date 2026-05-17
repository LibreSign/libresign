<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\ApprovalGroups;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicySpec;

final class ApprovalGroupsPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'approval_group';
	public const SYSTEM_APP_CONFIG_KEY = self::KEY;

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
				defaultSystemValue: ApprovalGroupsPolicyValue::encode(ApprovalGroupsPolicyValue::DEFAULT_GROUPS),
				allowedValues: static fn (PolicyContext $context): array => [],
				normalizer: static fn (mixed $rawValue): mixed => ApprovalGroupsPolicyValue::encode($rawValue),
				validator: static function (mixed $value): void {
					if (!is_string($value)) {
						throw new \InvalidArgumentException('Invalid value for ' . self::KEY);
					}

					$decoded = ApprovalGroupsPolicyValue::decode($value);
					if ($decoded === []) {
						throw new \InvalidArgumentException('At least one authorized group is required for ' . self::KEY);
					}
				},
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY,
				supportsUserPreference: false,
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
