<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\External;

use OCP\Files\File as NextcloudFile;

/**
 * One immutable snapshot of the authoritative input PDF.
 *
 * The Nextcloud file may change; check the original snapshot again before
 * publishing the result, never reconstructing from browser-owned bytes.
 */
final readonly class DocumentRevision {
	public function __construct(
		public NextcloudFile $node,
		#[\SensitiveParameter] public string $pdf,
		public string $revision,
	) {
	}

	public function assertUnchanged(): void {
		if ($this->node->getEtag() !== $this->revision
			|| !hash_equals(hash('sha256', $this->pdf), hash('sha256', $this->node->getContent()))) {
			throw new \DomainException('PDF source changed before CSC publication');
		}
	}
}
