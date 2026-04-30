<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\DefaultUserFolder;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicySpec;

final class DefaultUserFolderPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'default_user_folder';
	public const SYSTEM_APP_CONFIG_KEY = 'default_user_folder';
	public const DEFAULT_FOLDER = 'LibreSign';

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
				defaultSystemValue: self::DEFAULT_FOLDER,
				allowedValues: [],
				normalizer: static function (mixed $rawValue): string {
					$candidate = trim((string)$rawValue);
					return $candidate !== '' ? $candidate : self::DEFAULT_FOLDER;
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
