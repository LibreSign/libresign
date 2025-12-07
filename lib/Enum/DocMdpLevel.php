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
			self::CERTIFIED_FORM_FILLING => $l10n->t('Form filling and additional signatures'),
			self::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS => $l10n->t('Form filling, annotations and additional signatures'),
		};
	}

	public function getDescription(IL10N $l10n): string {
		return match($this) {
			self::NOT_CERTIFIED => $l10n->t('Approval signature - allows all modifications'),
			self::CERTIFIED_NO_CHANGES_ALLOWED => $l10n->t('Certifying signature - no modifications or additional signatures allowed'),
			self::CERTIFIED_FORM_FILLING => $l10n->t('Certifying signature - allows form filling and additional approval signatures'),
			self::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS => $l10n->t('Certifying signature - allows form filling, comments and additional approval signatures'),
		};
	}
}
