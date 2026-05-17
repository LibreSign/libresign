<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignatureText;

final class SignatureTextPolicyValue {
	/** @var array<string, mixed> */
	public const DEFAULTS = [
		'template' => '',
		'template_font_size' => 9.0,
		'signature_font_size' => 9.0,
		'signature_width' => 90.0,
		'signature_height' => 60.0,
		'render_mode' => 'default',
	];

	/**
	 * @param mixed $rawValue
	 * @return array<string, mixed>
	 */
	public static function normalize(mixed $rawValue, ?array $defaults = null): array {
		$defaults ??= self::DEFAULTS;

		if (is_string($rawValue)) {
			try {
				$decoded = json_decode($rawValue, true);
				if (is_array($decoded)) {
					$rawValue = $decoded;
				}
			} catch (\JsonException) {
				// Fallback to defaults
			}
		}

		if (!is_array($rawValue)) {
			return $defaults;
		}

		return [
			'template' => self::normalizeString($rawValue['template'] ?? $defaults['template']),
			'template_font_size' => self::normalizeFloat($rawValue['template_font_size'] ?? $defaults['template_font_size']),
			'signature_font_size' => self::normalizeFloat($rawValue['signature_font_size'] ?? $defaults['signature_font_size']),
			'signature_width' => self::normalizeFloat($rawValue['signature_width'] ?? $defaults['signature_width']),
			'signature_height' => self::normalizeFloat($rawValue['signature_height'] ?? $defaults['signature_height']),
			'render_mode' => self::normalizeRenderMode($rawValue['render_mode'] ?? $defaults['render_mode']),
		];
	}

	/**
	 * @param array<string, mixed> $value
	 */
	public static function encode(array $value): string {
		$normalized = self::normalize($value);
		return json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
	}

	private static function normalizeString(mixed $value): string {
		return (string)($value ?? '');
	}

	private static function normalizeFloat(mixed $value): float {
		$float = (float)($value ?? 0);
		return max(0.1, $float);
	}

	private static function normalizeRenderMode(mixed $value): string {
		$mode = (string)($value ?? 'default');
		return match ($mode) {
			'default', 'graphic', 'text' => $mode,
			default => 'default',
		};
	}
}
