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
	/**
	 * @param array<string, mixed> $metadata
	 * @psalm-return array<string, mixed>&LibresignValidateMetadata
	 */
	public static function normalize(array $metadata, string $fileName, int $totalPages): array {
		$metadata['p'] = max(0, $totalPages);

		$extension = pathinfo($fileName, PATHINFO_EXTENSION);
		if (!isset($metadata['extension']) || !is_string($metadata['extension']) || trim($metadata['extension']) === '') {
			$metadata['extension'] = is_string($extension) && $extension !== '' ? strtolower($extension) : 'pdf';
		}

		if (array_key_exists('original_file_deleted', $metadata) && !is_bool($metadata['original_file_deleted'])) {
			unset($metadata['original_file_deleted']);
		}

		if (array_key_exists('pdfVersion', $metadata) && !is_string($metadata['pdfVersion'])) {
			unset($metadata['pdfVersion']);
		}

		if (array_key_exists('status_changed_at', $metadata) && !is_string($metadata['status_changed_at'])) {
			unset($metadata['status_changed_at']);
		}

		if (array_key_exists('d', $metadata)) {
			$normalizedDimensions = self::normalizeDimensions($metadata['d']);
			if ($normalizedDimensions === null) {
				unset($metadata['d']);
			} else {
				$metadata['d'] = $normalizedDimensions;
			}
		}

		return $metadata;
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
