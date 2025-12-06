<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

use OCP\IL10N;

enum DocMdpLevel: int {
	case NONE = 0;
	case NO_CHANGES = 1;
	case FORM_FILL = 2;
	case FORM_FILL_AND_ANNOTATIONS = 3;

	public function isCertifying(): bool {
		return $this !== self::NONE;
	}

	public function getLabel(IL10N $l10n): string {
		return match($this) {
			self::NONE => $l10n->t('No certification'),
			self::NO_CHANGES => $l10n->t('No changes allowed'),
			self::FORM_FILL => $l10n->t('Form filling and additional signatures'),
			self::FORM_FILL_AND_ANNOTATIONS => $l10n->t('Form filling, annotations and additional signatures'),
		};
	}

	public function getDescription(IL10N $l10n): string {
		return match($this) {
			self::NONE => $l10n->t('Document is not certified. No restrictions on modifications.'),
			self::NO_CHANGES => $l10n->t('No changes allowed. Additional approval signatures are prohibited.'),
			self::FORM_FILL => $l10n->t('Form filling allowed. Additional approval signatures are allowed.'),
			self::FORM_FILL_AND_ANNOTATIONS => $l10n->t('Form filling and annotations allowed. Additional approval signatures are allowed.'),
		};
	}
}
