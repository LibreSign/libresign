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
	// Consolidated key (internal storage only)
	public const KEY = 'signature_text';
	public const SYSTEM_APP_CONFIG_KEY = 'signature_text';

	// Legacy/exposed keys (for policy API)
	public const KEY_TEMPLATE = 'signature_text_template';
	public const KEY_TEMPLATE_FONT_SIZE = 'template_font_size';
	public const KEY_SIGNATURE_WIDTH = 'signature_width';
	public const KEY_SIGNATURE_HEIGHT = 'signature_height';
	public const KEY_SIGNATURE_FONT_SIZE = 'signature_font_size';
	public const KEY_RENDER_MODE = 'signature_render_mode';

	// System app config keys (where they're actually stored)
	public const SYSTEM_APP_CONFIG_KEY_TEMPLATE = 'signature_text_template';
	public const SYSTEM_APP_CONFIG_KEY_TEMPLATE_FONT_SIZE = 'template_font_size';
	public const SYSTEM_APP_CONFIG_KEY_SIGNATURE_WIDTH = 'signature_width';
	public const SYSTEM_APP_CONFIG_KEY_SIGNATURE_HEIGHT = 'signature_height';
	public const SYSTEM_APP_CONFIG_KEY_SIGNATURE_FONT_SIZE = 'signature_font_size';
	public const SYSTEM_APP_CONFIG_KEY_RENDER_MODE = 'signature_render_mode';

	#[\Override]
	public function keys(): array {
		return [
			self::KEY,
			self::KEY_TEMPLATE,
			self::KEY_TEMPLATE_FONT_SIZE,
			self::KEY_SIGNATURE_WIDTH,
			self::KEY_SIGNATURE_HEIGHT,
			self::KEY_SIGNATURE_FONT_SIZE,
			self::KEY_RENDER_MODE,
		];
	}

	#[\Override]
	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		$normalizedKey = $this->normalizePolicyKey($policyKey);

		return match ($normalizedKey) {
			self::KEY => new PolicySpec(
				key: self::KEY,
				defaultSystemValue: $this->encodeConsolidatedValue($this->defaultConsolidatedValue()),
				allowedValues: [],
				normalizer: fn (mixed $rawValue): string => $this->encodeConsolidatedValue(
					$this->normalizeConsolidatedValue($rawValue),
				),
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY,
			),
			self::KEY_TEMPLATE => new PolicySpec(
				key: self::KEY_TEMPLATE,
				defaultSystemValue: '',
				allowedValues: [],
				normalizer: fn (mixed $rawValue): string => (string)$rawValue,
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY_TEMPLATE,
			),
			self::KEY_TEMPLATE_FONT_SIZE => new PolicySpec(
				key: self::KEY_TEMPLATE_FONT_SIZE,
				defaultSystemValue: 9.0,
				allowedValues: [],
				normalizer: fn (mixed $rawValue): float => (float)$rawValue,
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY_TEMPLATE_FONT_SIZE,
			),
			self::KEY_SIGNATURE_WIDTH => new PolicySpec(
				key: self::KEY_SIGNATURE_WIDTH,
				defaultSystemValue: 90.0,
				allowedValues: [],
				normalizer: fn (mixed $rawValue): float => (float)$rawValue,
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY_SIGNATURE_WIDTH,
			),
			self::KEY_SIGNATURE_HEIGHT => new PolicySpec(
				key: self::KEY_SIGNATURE_HEIGHT,
				defaultSystemValue: 60.0,
				allowedValues: [],
				normalizer: fn (mixed $rawValue): float => (float)$rawValue,
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY_SIGNATURE_HEIGHT,
			),
			self::KEY_SIGNATURE_FONT_SIZE => new PolicySpec(
				key: self::KEY_SIGNATURE_FONT_SIZE,
				defaultSystemValue: 9.0,
				allowedValues: [],
				normalizer: fn (mixed $rawValue): float => (float)$rawValue,
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY_SIGNATURE_FONT_SIZE,
			),
			self::KEY_RENDER_MODE => new PolicySpec(
				key: self::KEY_RENDER_MODE,
				defaultSystemValue: 'default',
				allowedValues: ['default', 'graphic', 'text'],
				normalizer: function (mixed $rawValue): string {
					$value = (string)$rawValue;
					$allowed = ['default', 'graphic', 'text'];
					return in_array($value, $allowed, true) ? $value : 'default';
				},
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY_RENDER_MODE,
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

	/**
	 * @return array<string, mixed>
	 */
	private function defaultConsolidatedValue(): array {
		return [
			'template' => '',
			'template_font_size' => 9.0,
			'signature_font_size' => 9.0,
			'signature_width' => 90.0,
			'signature_height' => 60.0,
			'render_mode' => 'default',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function normalizeConsolidatedValue(mixed $rawValue): array {
		$defaults = $this->defaultConsolidatedValue();

		if (is_string($rawValue)) {
			$decoded = json_decode($rawValue, true);
			if (is_array($decoded)) {
				$rawValue = $decoded;
			}
		}

		if (!is_array($rawValue)) {
			return $defaults;
		}

		$renderMode = (string)($rawValue['render_mode'] ?? $defaults['render_mode']);
		if (!in_array($renderMode, ['default', 'graphic', 'text'], true)) {
			$renderMode = 'default';
		}

		return [
			'template' => (string)($rawValue['template'] ?? $defaults['template']),
			'template_font_size' => max(0.1, (float)($rawValue['template_font_size'] ?? $defaults['template_font_size'])),
			'signature_font_size' => max(0.1, (float)($rawValue['signature_font_size'] ?? $defaults['signature_font_size'])),
			'signature_width' => max(0.1, (float)($rawValue['signature_width'] ?? $defaults['signature_width'])),
			'signature_height' => max(0.1, (float)($rawValue['signature_height'] ?? $defaults['signature_height'])),
			'render_mode' => $renderMode,
		];
	}

	/**
	 * @param array<string, mixed> $value
	 */
	private function encodeConsolidatedValue(array $value): string {
		return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
	}
}
