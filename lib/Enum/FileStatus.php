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

	public function getLabel(IL10N $l10n): string {
		return match($this) {
			// TRANSLATORS Name of the status when document is not a LibreSign file
			self::NOT_LIBRESIGN_FILE => $l10n->t('not LibreSign file'),
			// TRANSLATORS Name of the status that the document is still as a draft
			self::DRAFT => $l10n->t('draft'),
			// TRANSLATORS Name of the status that the document can be signed
			self::ABLE_TO_SIGN => $l10n->t('available for signature'),
			// TRANSLATORS Name of the status when the document has already been partially signed
			self::PARTIAL_SIGNED => $l10n->t('partially signed'),
			// TRANSLATORS Name of the status when the document has been completely signed
			self::SIGNED => $l10n->t('signed'),
			// TRANSLATORS Name of the status when the document was deleted
			self::DELETED => $l10n->t('deleted'),
		};
	}
}
