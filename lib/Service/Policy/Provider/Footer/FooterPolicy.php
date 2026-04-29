<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\Footer;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicySpec;

final class FooterPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'add_footer';
	public const SYSTEM_APP_CONFIG_KEY = 'add_footer';

	#[\Override]
	public function keys(): array {
		return [
			self::KEY,
		];
	}

	#[\Override]
	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		$instanceBaseTemplate = $this->resolveInstanceBaseTemplate();
		return match ($this->normalizePolicyKey($policyKey)) {
			self::KEY => new PolicySpec(
				key: self::KEY,
				defaultSystemValue: FooterPolicyValue::encode(FooterPolicyValue::defaults($instanceBaseTemplate)),
				allowedValues: static fn (): array => [],
				normalizer: function (mixed $rawValue) use ($instanceBaseTemplate): mixed {
					return FooterPolicyValue::encode(FooterPolicyValue::normalize($rawValue, $instanceBaseTemplate));
				},
				validator: function (mixed $value, PolicyContext $context) use ($instanceBaseTemplate): void {
					if (!is_string($value) || trim($value) === '') {
						throw new \InvalidArgumentException('Invalid value for ' . self::KEY);
					}

					$decoded = json_decode($value, true);
					if (!is_array($decoded)) {
						throw new \InvalidArgumentException('Invalid value for ' . self::KEY);
					}

					if (!self::canManageTechnicalFooterSettings($context)) {
						$normalized = FooterPolicyValue::normalize($decoded, $instanceBaseTemplate);
						if ($normalized['validationSite'] !== '') {
							throw new \InvalidArgumentException('Validation URL override is not allowed for this actor');
						}
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

	private static function canManageTechnicalFooterSettings(PolicyContext $context): bool {
		$capabilities = $context->getActorCapabilities();

		return ($capabilities['canManageSystemPolicies'] ?? false) === true
			|| ($capabilities['canManageGroupPolicies'] ?? false) === true;
	}

	private function resolveInstanceBaseTemplate(): string {
		$defaultTemplatePath = __DIR__ . '/../../../../Handler/Templates/footer.twig';
		$defaultTemplate = @file_get_contents($defaultTemplatePath);

		return is_string($defaultTemplate) ? $defaultTemplate : '';
	}
}
