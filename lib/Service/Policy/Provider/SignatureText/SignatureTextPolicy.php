<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignatureText;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicySpec;

final class SignatureTextPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'signature_text';
	public const SYSTEM_APP_CONFIG_KEY = 'signature_text';

	// Legacy keys for migration purposes (will be consolidated into KEY)
	public const KEY_TEMPLATE = 'signature_text_template';
	public const KEY_TEMPLATE_FONT_SIZE = 'template_font_size';
	public const KEY_SIGNATURE_WIDTH = 'signature_width';
	public const KEY_SIGNATURE_HEIGHT = 'signature_height';
	public const KEY_SIGNATURE_FONT_SIZE = 'signature_font_size';
	public const KEY_RENDER_MODE = 'signature_render_mode';

	public const SYSTEM_APP_CONFIG_KEY_TEMPLATE = 'signature_text_template';
	public const SYSTEM_APP_CONFIG_KEY_TEMPLATE_FONT_SIZE = 'template_font_size';
	public const SYSTEM_APP_CONFIG_KEY_SIGNATURE_WIDTH = 'signature_width';
	public const SYSTEM_APP_CONFIG_KEY_SIGNATURE_HEIGHT = 'signature_height';
	public const SYSTEM_APP_CONFIG_KEY_SIGNATURE_FONT_SIZE = 'signature_font_size';
	public const SYSTEM_APP_CONFIG_KEY_RENDER_MODE = 'signature_render_mode';

	#[\Override]
	public function keys(): array {
		return [self::KEY];
	}

	#[\Override]
	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		$normalizedKey = $this->normalizePolicyKey($policyKey);

		return match ($normalizedKey) {
			self::KEY => new PolicySpec(
				key: self::KEY,
				defaultSystemValue: SignatureTextPolicyValue::encode(SignatureTextPolicyValue::DEFAULTS),
				allowedValues: [],
				normalizer: function (mixed $rawValue): string {
					$normalized = SignatureTextPolicyValue::normalize($rawValue);
					return SignatureTextPolicyValue::encode($normalized);
				},
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY,
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . $normalizedKey),
		};
	}

	private function normalizePolicyKey(string|\BackedEnum $policyKey): string {
		if ($policyKey instanceof \BackedEnum) {
			return (string)$policyKey->value;
		}

		return $policyKey;
	}
}

	private function normalizePolicyKey(string|\BackedEnum $policyKey): string {
		if ($policyKey instanceof \BackedEnum) {
			return (string)$policyKey->value;
		}

		return $policyKey;
	}
}
