<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\IdentificationDocuments;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicySpec;

final class IdentificationDocumentsPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'identification_documents';
	public const SYSTEM_APP_CONFIG_KEY = 'identification_documents';

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
				defaultSystemValue: false,
				allowedValues: [
					false,
					true,
				],
				normalizer: static function (mixed $rawValue): mixed {
					if (is_bool($rawValue)) {
						return $rawValue;
					}

					if (is_int($rawValue)) {
						return $rawValue !== 0;
					}

					if (is_string($rawValue)) {
						$value = strtolower(trim($rawValue));
						if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
							return true;
						}

						if (in_array($value, ['0', 'false', 'no', 'off', ''], true)) {
							return false;
						}
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