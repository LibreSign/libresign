<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureRejection;

use OCA\Libresign\Enum\SignerDisplayStatus;

/**
 * Viewer-specific presentation of one signer, built while preparing a
 * response and never stored: the real status is null when the viewer may
 * not know it.
 */
final class SignerPresentation {
	/**
	 * @param array{rejectedAt: string, comment?: string, commentPrivate?: bool}|null $rejection
	 */
	public function __construct(
		public readonly SignerDisplayStatus $displayStatus,
		public readonly ?int $status,
		public readonly string $statusText,
		public readonly ?array $rejection,
	) {
	}

	/**
	 * Write the presentation into a signer array, removing what must not
	 * be disclosed.
	 *
	 * @param array<string, mixed> $signer
	 * @return array<string, mixed>
	 */
	public function applyTo(array $signer): array {
		unset($signer['status'], $signer['rejection']);
		$signer['displayStatus'] = $this->displayStatus->value;
		$signer['statusText'] = $this->statusText;
		if ($this->status !== null) {
			$signer['status'] = $this->status;
		}
		if ($this->rejection !== null) {
			$signer['rejection'] = $this->rejection;
		}
		return $signer;
	}

	public function applyToObject(\stdClass $signer): void {
		unset($signer->status, $signer->rejection);
		$signer->displayStatus = $this->displayStatus->value;
		$signer->statusText = $this->statusText;
		if ($this->status !== null) {
			$signer->status = $this->status;
		}
		if ($this->rejection !== null) {
			$signer->rejection = $this->rejection;
		}
	}
}
