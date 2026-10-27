<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\NotificationDelivery;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Provider\Helper\PolicyKeyNormalizer;

/** Delivery stays synchronous until a supported async executor exists. */
final class NotificationDeliveryModePolicy implements IPolicyDefinitionProvider {
	public const KEY = 'notification_delivery_mode';
	public const MODE_SYNC = 'sync';
	public const MODE_ASYNC = 'async';

	#[\Override]
	public function keys(): array {
		return [self::KEY];
	}

	#[\Override]
	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		if (PolicyKeyNormalizer::normalize($policyKey) !== self::KEY) {
			throw new \InvalidArgumentException('Unknown policy key: ' . PolicyKeyNormalizer::normalize($policyKey));
		}

		return new PolicySpec(
			key: self::KEY,
			defaultSystemValue: self::MODE_SYNC,
			allowedValues: [self::MODE_SYNC, self::MODE_ASYNC],
			normalizer: static fn (mixed $raw): string => strtolower(trim((string)$raw)),
			validator: static function (mixed $value): void {
				if (!in_array($value, [self::MODE_SYNC, self::MODE_ASYNC], true)) {
					throw new \InvalidArgumentException('Invalid notification delivery mode');
				}
			},
			persistenceValidator: static function (mixed $value): void {
				if ($value === self::MODE_ASYNC) {
					throw new \InvalidArgumentException('Async notification delivery is not available yet');
				}
			},
			appConfigKey: self::KEY,
			supportsUserPreference: false,
			supportsRequestOverride: false,
			requestLifecycle: PolicySpec::LIFECYCLE_RUNTIME,
			supportedScopes: [PolicySpec::SCOPE_SYSTEM],
		);
	}
}
