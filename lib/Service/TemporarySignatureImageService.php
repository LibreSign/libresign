<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Exception\LibresignException;
use OCP\IL10N;
use OCP\ITempManager;

/**
 * Materializes a signature image for the current signing operation.
 *
 * Only stored image contents are suitable for rebuilding a missing temporary
 * copy; the temporary path is not durable across Nextcloud requests or jobs.
 */
class TemporarySignatureImageService {
	public function __construct(
		private ITempManager $tempManager,
		private IL10N $l10n,
	) {
	}

	public function create(int $nodeId, string $content): string {
		$path = $this->tempManager->getTemporaryFile('_' . $nodeId . '.png');

		if (!$path || $content === '' || @file_put_contents($path, $content) !== strlen($content) || !$this->isAvailable($path)) {
			throw $this->imageNotAvailable();
		}

		return $path;
	}

	public function isAvailable(string $path): bool {
		if ($path === '') {
			return false;
		}
		clearstatcache(true, $path);
		return is_file($path) && is_readable($path) && @filesize($path) > 0;
	}

	/** A missing image is valid for text-only and click-to-sign elements. */
	public function requireAvailable(string $path): void {
		if ($path !== '' && !$this->isAvailable($path)) {
			throw $this->imageNotAvailable();
		}
	}

	private function imageNotAvailable(): LibresignException {
		// TRANSLATORS Error shown when LibreSign cannot prepare or read the visible signature image.
		return new LibresignException($this->l10n->t('Temporary signature image is not available. Please try signing again.'));
	}
}
