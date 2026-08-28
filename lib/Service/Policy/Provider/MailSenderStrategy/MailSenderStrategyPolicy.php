<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\MailSenderStrategy;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Provider\Helper\PolicyKeyNormalizer;

/**
 * Controls which mail account LibreSign uses to send signature request
 * notifications: the system mailer or the account of the requester.
 */
final class MailSenderStrategyPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'mail_sender_strategy';
	public const SYSTEM_APP_CONFIG_KEY = self::KEY;

	public const STRATEGY_SYSTEM = 'system';
	public const STRATEGY_REQUESTER = 'requester';

	private const STRATEGIES = [
		self::STRATEGY_SYSTEM,
		self::STRATEGY_REQUESTER,
	];

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
				defaultSystemValue: self::STRATEGY_SYSTEM,
				allowedValues: self::STRATEGIES,
				normalizer: static fn (mixed $rawValue): string => strtolower(trim((string)$rawValue)),
				validator: static function (mixed $value): void {
					if (!is_string($value) || !in_array($value, self::STRATEGIES, true)) {
						throw new \InvalidArgumentException('Invalid value for ' . self::KEY);
					}
				},
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY,
				supportsUserPreference: false,
				supportedScopes: [PolicySpec::SCOPE_SYSTEM],
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . PolicyKeyNormalizer::normalize($policyKey)),
		};
	}
}
