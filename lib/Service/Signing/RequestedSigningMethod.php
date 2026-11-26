<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Db\SignRequest;

/** A requester's per-recipient choice: one signature, never two competing signature paths. */
enum RequestedSigningMethod: string {
	case LIBRESIGN = 'libresign';
	case EXTERNAL_CERTIFICATE = 'external_certificate';
	case SIGNER_CHOICE = 'signer_choice';

	public const METADATA_KEY = 'signingMethodRequirement';

	/** Older requests without an explicit requirement retain their existing choices. */
	public static function forRequest(SignRequest $request): self {
		$metadata = $request->getMetadata() ?? [];
		if (!array_key_exists(self::METADATA_KEY, $metadata)) {
			return self::SIGNER_CHOICE;
		}
		return self::fromInput($metadata[self::METADATA_KEY]);
	}

	public static function fromInput(mixed $value): self {
		if (!is_string($value) || self::tryFrom($value) === null) {
			throw new \InvalidArgumentException('Unsupported signing method requirement');
		}
		return self::from($value);
	}

	public function allowsLibreSign(): bool {
		return $this !== self::EXTERNAL_CERTIFICATE;
	}

	public function allowsExternalCertificate(): bool {
		return $this !== self::LIBRESIGN;
	}
}
