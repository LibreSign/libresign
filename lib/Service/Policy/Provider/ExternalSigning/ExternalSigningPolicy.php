<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\ExternalSigning;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Provider\Helper\PolicyKeyNormalizer;

/**
 * Account and group policy for whether external signing is permitted.
 *
 * A configured CSC connection is never proof of signer authorization.
 * Actual signing still requires request access, an enabled integration and
 * provider-specific consent checks on the server.
 */
final class ExternalSigningPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'external_signing_enabled';

	#[\Override]
	public function keys(): array {
		return [self::KEY];
	}

	#[\Override]
	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		return match (PolicyKeyNormalizer::normalize($policyKey)) {
			self::KEY => new PolicySpec(
				key: self::KEY,
				defaultSystemValue: false,
				allowedValues: [false, true],
				normalizer: static function (mixed $value): mixed {
					return match ($value) {
						true, 'true', 1, '1' => true,
						false, 'false', 0, '0' => false,
						default => $value,
					};
				},
				appConfigKey: 'policy.external_signing_enabled.system',
				supportsRequestOverride: false,
				requestLifecycle: PolicySpec::LIFECYCLE_RUNTIME,
				supportsUserPreference: false,
				supportedScopes: [
					PolicySpec::SCOPE_SYSTEM,
					PolicySpec::SCOPE_GROUP,
					PolicySpec::SCOPE_USER,
				],
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . PolicyKeyNormalizer::normalize($policyKey)),
		};
	}
}
