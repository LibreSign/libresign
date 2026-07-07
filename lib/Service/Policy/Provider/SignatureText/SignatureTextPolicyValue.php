<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignatureText;

final class SignatureTextPolicyValue {
	public const DEFAULT_TEMPLATE_FONT_SIZE = 9.8;
	public const DEFAULT_SIGNATURE_FONT_SIZE = 20.0;
	public const DEFAULT_SIGNATURE_WIDTH = 350.0;
	public const DEFAULT_SIGNATURE_HEIGHT = 100.0;

	/**
	 * @var array{
	 *     template: string,
	 *     template_font_size: float,
	 *     signature_font_size: float,
	 *     signature_width: float,
	 *     signature_height: float,
	 *     background_type: 'default'|'custom'|'deleted',
	 *     render_mode: 'default'|'graphic'|'text'|'description_only',
	 * }
	 */
	public const DEFAULTS = [
		'template' => '',
		'template_font_size' => self::DEFAULT_TEMPLATE_FONT_SIZE,
		'signature_font_size' => self::DEFAULT_SIGNATURE_FONT_SIZE,
		'signature_width' => self::DEFAULT_SIGNATURE_WIDTH,
		'signature_height' => self::DEFAULT_SIGNATURE_HEIGHT,
		'background_type' => 'default',
		'render_mode' => 'default',
	];

	/**
	 * @param mixed $rawValue
	 * @param null|array{
	 *     template: string,
	 *     template_font_size: float,
	 *     signature_font_size: float,
	 *     signature_width: float,
	 *     signature_height: float,
	 *     background_type: 'default'|'custom'|'deleted',
	 *     render_mode: 'default'|'graphic'|'text'|'description_only',
	 * } $defaults
	 * @return array{
	 *     template: string,
	 *     template_font_size: float,
	 *     signature_font_size: float,
	 *     signature_width: float,
	 *     signature_height: float,
	 *     background_type: 'default'|'custom'|'deleted',
	 *     render_mode: 'default'|'graphic'|'text'|'description_only',
	 * }
	 */
	public static function normalize(mixed $rawValue, ?array $defaults = null): array {
		$defaults = $defaults === null ? self::DEFAULTS : array_replace(self::DEFAULTS, $defaults);

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
			'background_type' => self::normalizeBackgroundType($rawValue['background_type'] ?? $defaults['background_type']),
			'render_mode' => self::normalizeRenderMode($rawValue['render_mode'] ?? $defaults['render_mode']),
		];
	}

	/**
	 * @param array{
	 *     template: string,
	 *     template_font_size: float,
	 *     signature_font_size: float,
	 *     signature_width: float,
	 *     signature_height: float,
	 *     background_type: 'default'|'custom'|'deleted',
	 *     render_mode: 'default'|'graphic'|'text'|'description_only',
	 * } $value
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

	/** @return 'default'|'graphic'|'text'|'description_only' */
	private static function normalizeRenderMode(mixed $value): string {
		$mode = (string)($value ?? 'default');
		return match ($mode) {
			'default', 'graphic', 'text', 'description_only' => $mode,
			default => 'default',
		};
	}

	/** @return 'default'|'custom'|'deleted' */
	private static function normalizeBackgroundType(mixed $value): string {
		$mode = (string)($value ?? 'default');
		return match ($mode) {
			'default', 'custom', 'deleted' => $mode,
			default => 'default',
		};
	}
}
