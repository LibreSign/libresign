<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

use OCP\IL10N;

enum ParticipantRole: string {
	case SIGNER = 'signer';
	case OBSERVER = 'observer';

	public function canSign(): bool {
		return $this === self::SIGNER;
	}

	public function getLabel(IL10N $l10n): string {
		return match ($this) {
			// TRANSLATORS Participant role label for someone who must digitally sign the document.
			self::SIGNER => $l10n->t('Signer'),
			// TRANSLATORS Participant role label for someone who can only view the document and track progress.
			self::OBSERVER => $l10n->t('Observer'),
		};
	}

	public static function fromNullable(?string $value): self {
		if ($value === null || $value === '') {
			return self::SIGNER;
		}

		return self::tryFrom($value) ?? self::SIGNER;
	}
}
