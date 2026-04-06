<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

final class ValidationMetadataNormalizer {
	/**
	 * @param array<string, mixed> $metadata
	 * @return array<string, mixed>
	 */
	public static function normalize(array $metadata, string $fileName, int $totalPages): array {
		$metadata['p'] = $totalPages;

		$extension = pathinfo($fileName, PATHINFO_EXTENSION);
		if (!isset($metadata['extension']) || !is_string($metadata['extension']) || trim($metadata['extension']) === '') {
			$metadata['extension'] = is_string($extension) && $extension !== '' ? strtolower($extension) : 'pdf';
		}

		return $metadata;
	}
}
