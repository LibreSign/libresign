<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\OrganizationSeals;

use OCA\Libresign\Service\OrganizationSeal\OrganizationSealStore;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Provider\Helper\PolicyKeyNormalizer;

/**
 * Organization seals applied to a document after its last signature.
 *
 * The value is the list of the configured seal ids. The default is an empty
 * list, so nothing is sealed unless an administrator sets a default or allows
 * requesters to choose seals per request.
 */
final class OrganizationSealsPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'organization_seals';
	public const SYSTEM_APP_CONFIG_KEY = 'organization_seals_policy';

	public function __construct(
		private OrganizationSealStore $sealStore,
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
				defaultSystemValue: OrganizationSealsPolicyValue::encode([]),
				allowedValues: static fn (): array => [],
				supportsRequestOverride: true,
				requestLifecycle: PolicySpec::LIFECYCLE_REQUEST_SNAPSHOT,
				normalizer: static fn (mixed $rawValue): string => OrganizationSealsPolicyValue::encode($rawValue),
				validator: static function (mixed $value): void {
					if (!is_string($value)) {
						throw new \InvalidArgumentException('Invalid value for ' . self::KEY);
					}
					if (count(OrganizationSealsPolicyValue::decode($value)) > OrganizationSealsPolicyValue::MAX_SEALS) {
						throw new \InvalidArgumentException('Too many seals for ' . self::KEY);
					}
				},
				persistenceValidator: function (mixed $value): void {
					foreach (OrganizationSealsPolicyValue::decode($value) as $id) {
						if (!$this->sealStore->exists($id)) {
							throw new \InvalidArgumentException('Unknown seal in ' . self::KEY);
						}
					}
				},
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY,
				supportsUserPreference: false,
				backendOnly: true,
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . PolicyKeyNormalizer::normalize($policyKey)),
		};
	}
}
