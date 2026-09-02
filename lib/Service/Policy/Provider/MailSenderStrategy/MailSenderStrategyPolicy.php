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
use OCP\Mail\Provider\IManager as IMailProviderManager;

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

	public function __construct(
		private IMailProviderManager $mailProviderManager,
	) {
	}

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
				resolvedStateMeta: fn (): array => [
					'mailProviderAvailable' => $this->mailProviderManager->has(),
				],
				supportedScopes: [PolicySpec::SCOPE_SYSTEM],
				// The requester strategy can only be configured while a mail provider
				// is available. Once stored, runtime resolution keeps the value and
				// MailService falls back to the system mailer when the environment
				// changes later (provider removed, account deleted, sending failure).
				persistenceValidator: function (mixed $value): void {
					if ($value === self::STRATEGY_REQUESTER && !$this->mailProviderManager->has()) {
						throw new \InvalidArgumentException('The requester strategy requires an available mail provider');
					}
				},
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . PolicyKeyNormalizer::normalize($policyKey)),
		};
	}
}
