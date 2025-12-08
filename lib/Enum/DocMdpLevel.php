<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

use OCP\IL10N;

enum DocMdpLevel: int {
	case NOT_CERTIFIED = 0;
	case CERTIFIED_NO_CHANGES_ALLOWED = 1;
	case CERTIFIED_FORM_FILLING = 2;
	case CERTIFIED_FORM_FILLING_AND_ANNOTATIONS = 3;

	public function isCertifying(): bool {
		return $this !== self::NOT_CERTIFIED;
	}

	public function getLabel(IL10N $l10n): string {
		return match($this) {
			self::NOT_CERTIFIED => $l10n->t('No certification'),
			self::CERTIFIED_NO_CHANGES_ALLOWED => $l10n->t('No changes allowed'),
			self::CERTIFIED_FORM_FILLING => $l10n->t('Form filling allowed'),
			self::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS => $l10n->t('Form filling and commenting allowed'),
		};
	}

	public function getDescription(IL10N $l10n): string {
		return match($this) {
			self::NOT_CERTIFIED => $l10n->t('The document is not certified; edits and new signatures are allowed, but any change will mark previous signatures as modified.'),
			self::CERTIFIED_NO_CHANGES_ALLOWED => $l10n->t('After the first signature, no further edits or signatures are allowed; any change invalidates the certification.'),
			self::CERTIFIED_FORM_FILLING => $l10n->t('After the first signature, only form filling and additional signatures are allowed; other changes invalidate the certification.'),
			self::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS => $l10n->t('After the first signature, form filling, comments, and additional signatures are allowed; other changes invalidate the certification.'),
		};
	}
}
