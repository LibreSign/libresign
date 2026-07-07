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
			// TRANSLATORS Signer workflow status shown before the request is ready to be signed.
			self::DRAFT => $l10n->t('Draft'),
			// TRANSLATORS Signer workflow status shown when the signer is currently allowed to apply their digital signature.
			self::ABLE_TO_SIGN => $l10n->t('Ready to sign'),
			// TRANSLATORS Signer workflow status shown after this signer has successfully signed the document.
			self::SIGNED => $l10n->t('Signed'),
		};
	}
}
