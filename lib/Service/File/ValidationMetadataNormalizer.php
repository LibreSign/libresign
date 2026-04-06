<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

/**
 * @psalm-import-type LibresignValidateMetadata from \OCA\Libresign\ResponseDefinitions
 */
final class ValidationMetadataNormalizer {
	private const OPTIONAL_SCALAR_TYPE_GUARDS = [
		'original_file_deleted' => 'is_bool',
		'pdfVersion' => 'is_string',
		'status_changed_at' => 'is_string',
	];

	/**
	 * @param array<string, mixed> $metadata
	 * @psalm-return array<string, mixed>&LibresignValidateMetadata
	 */
	public static function normalize(array $metadata, string $fileName, int $totalPages): array {
		$normalized = $metadata;
		$normalized['p'] = self::normalizePageCount($totalPages);
		$normalized['extension'] = self::normalizeExtension($normalized, $fileName);

		self::normalizeOptionalScalarFields($normalized);
		self::normalizeDimensionsField($normalized);

		return $normalized;
	}

	private static function normalizePageCount(int $totalPages): int {
		return max(0, $totalPages);
	}

	/**
	 * @param array<string, mixed> $metadata
	 */
	private static function normalizeExtension(array $metadata, string $fileName): string {
		if (isset($metadata['extension']) && is_string($metadata['extension']) && trim($metadata['extension']) !== '') {
			return $metadata['extension'];
		}

		$extension = pathinfo($fileName, PATHINFO_EXTENSION);
		return is_string($extension) && $extension !== '' ? strtolower($extension) : 'pdf';
	}

	/**
	 * @param array<string, mixed> $metadata
	 */
	private static function normalizeOptionalScalarFields(array &$metadata): void {
		foreach (self::OPTIONAL_SCALAR_TYPE_GUARDS as $key => $guard) {
			if (!array_key_exists($key, $metadata)) {
				continue;
			}

			if (!is_callable($guard) || !$guard($metadata[$key])) {
				unset($metadata[$key]);
			}
		}
	}

	/**
	 * @param array<string, mixed> $metadata
	 */
	private static function normalizeDimensionsField(array &$metadata): void {
		if (!array_key_exists('d', $metadata)) {
			return;
		}

		$normalizedDimensions = self::normalizeDimensions($metadata['d']);
		if ($normalizedDimensions === null) {
			unset($metadata['d']);
			return;
		}

		$metadata['d'] = $normalizedDimensions;
	}

	/**
	 * @return list<array{w: float, h: float}>|null
	 */
	private static function normalizeDimensions(mixed $dimensions): ?array {
		if (!is_array($dimensions)) {
			return null;
		}

		$normalized = [];
		foreach ($dimensions as $dimension) {
			if (!is_array($dimension)
				|| !array_key_exists('w', $dimension)
				|| !array_key_exists('h', $dimension)
				|| !is_numeric($dimension['w'])
				|| !is_numeric($dimension['h'])) {
				continue;
			}

			$normalized[] = [
				'w' => (float)$dimension['w'],
				'h' => (float)$dimension['h'],
			];
		}

		return $normalized === [] ? null : $normalized;
	}
}
