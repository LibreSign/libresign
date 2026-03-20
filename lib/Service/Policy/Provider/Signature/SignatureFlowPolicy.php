<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\Signature;

use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicySpec;

use function is_int;

final class SignatureFlowPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'signature_flow';
	public const SYSTEM_APP_CONFIG_KEY = 'policy.signature_flow.system';

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
				defaultSystemValue: SignatureFlow::NONE->value,
				allowedValues: [
					SignatureFlow::NONE->value,
					SignatureFlow::PARALLEL->value,
					SignatureFlow::ORDERED_NUMERIC->value,
				],
				normalizer: static function (mixed $rawValue): mixed {
					if ($rawValue instanceof SignatureFlow) {
						return $rawValue->value;
					}

					if (is_int($rawValue)) {
						return SignatureFlow::fromNumeric($rawValue)->value;
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
