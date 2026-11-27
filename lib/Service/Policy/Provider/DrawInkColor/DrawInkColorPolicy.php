<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\DrawInkColor;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Provider\Helper\PolicyKeyNormalizer;

/**
 * Initial ink color of the draw signature pad. The signer can still
 * choose another color in the pad, so only the system scope is supported.
 */
final class DrawInkColorPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'draw_ink_color';
	public const SYSTEM_APP_CONFIG_KEY = self::KEY;
	public const DEFAULT_COLOR = '#000000';
	private const COLOR_PATTERN = '/^#[0-9a-f]{6}$/';

	#[\Override]
	public function keys(): array {
		return [
			self::KEY,
		];
	}

	#[\Override]
	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		return match (PolicyKeyNormalizer::normalize($policyKey)) {
			self::KEY => new PolicySpec(
				key: self::KEY,
				defaultSystemValue: self::DEFAULT_COLOR,
				allowedValues: [],
				normalizer: static fn (mixed $rawValue): string => strtolower(trim((string)$rawValue)),
				validator: static function (mixed $value): void {
					if (!is_string($value) || preg_match(self::COLOR_PATTERN, $value) !== 1) {
						throw new \InvalidArgumentException('Invalid value for ' . self::KEY);
					}
				},
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY,
				supportsUserPreference: false,
				supportsRequestOverride: false,
				requestLifecycle: PolicySpec::LIFECYCLE_RUNTIME,
				supportedScopes: [PolicySpec::SCOPE_SYSTEM],
				supportsGroupAdminDelegation: false,
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . PolicyKeyNormalizer::normalize($policyKey)),
		};
	}
}
