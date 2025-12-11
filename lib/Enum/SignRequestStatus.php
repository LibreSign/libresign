<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

use OCP\IL10N;

enum SignRequestStatus: int {
	case DRAFT = 0;
	case ABLE_TO_SIGN = 1;
	case SIGNED = 2;

	public function getLabel(IL10N $l10n): string {
		return match($this) {
			// TRANSLATORS Name of the status when signer document is in draft state
			self::DRAFT => $l10n->t('Draft'),
			// TRANSLATORS Name of the status when signer can sign the document
			self::ABLE_TO_SIGN => $l10n->t('Pending'),
			// TRANSLATORS Name of the status when signer has already signed
			self::SIGNED => $l10n->t('Signed'),
		};
	}
}
