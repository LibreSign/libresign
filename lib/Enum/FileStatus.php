<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

use OCP\IL10N;

/**
 * File status enum
 *
 * Represents all possible states a LibreSign file can be in
 */
enum FileStatus: int {
	case NOT_LIBRESIGN_FILE = -1;
	case DRAFT = 0;
	case ABLE_TO_SIGN = 1;
	case PARTIAL_SIGNED = 2;
	case SIGNED = 3;
	case DELETED = 4;
	case SIGNING_IN_PROGRESS = 5;

	public function getLabel(IL10N $l10n): string {
		return match($this) {
			// TRANSLATORS File status shown when the file is not part of any LibreSign signing flow.
			self::NOT_LIBRESIGN_FILE => $l10n->t('Not LibreSign file'),
			// TRANSLATORS File status shown while the signature request is still being prepared.
			self::DRAFT => $l10n->t('Draft'),
			// TRANSLATORS File status shown when at least one signer can sign now.
			self::ABLE_TO_SIGN => $l10n->t('Ready to sign'),
			// TRANSLATORS File status shown when some required signers have signed, but not all.
			self::PARTIAL_SIGNED => $l10n->t('Partially signed'),
			// TRANSLATORS File status shown when all required signatures are complete.
			self::SIGNED => $l10n->t('Signed'),
			// TRANSLATORS File status shown when the LibreSign file record was deleted.
			self::DELETED => $l10n->t('Deleted'),
			// TRANSLATORS File status shown during asynchronous background signing operations.
			self::SIGNING_IN_PROGRESS => $l10n->t('Signing in progress'),
		};
	}
}
