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
				defaultSystemValue: [
					'enabled' => false,
					'approvers' => ['admin'],
				],
				allowedValues: static fn (): array => [],
				normalizer: static fn (mixed $rawValue): array => \OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicyValue::normalize($rawValue, false),
				validator: static function (mixed $value): void {
					if (!is_array($value)) {
						throw new \InvalidArgumentException('Invalid value for ' . self::KEY);
					}
					if (!array_key_exists('enabled', $value)) {
						throw new \InvalidArgumentException('Missing "enabled" key in ' . self::KEY);
					}
					if (!array_key_exists('approvers', $value)) {
						throw new \InvalidArgumentException('Missing "approvers" key in ' . self::KEY);
					}
					if (!is_array($value['approvers'])) {
						throw new \InvalidArgumentException('Approvers must be an array');
					}
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
