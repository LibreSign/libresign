<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

use OCP\IL10N;

/**
 * What the current viewer is allowed to know about a signer. It is an API
 * presentation value only: SignRequestStatus stays the workflow state and
 * nothing here is persisted or used for authorization.
 */
enum SignerDisplayStatus: string {
	case DRAFT = 'draft';
	case READY_TO_SIGN = 'ready_to_sign';
	case SIGNED = 'signed';
	case REJECTED = 'rejected';
	case OBSERVING = 'observing';
	/** The signer has not signed and the viewer may not know anything more specific. */
	case NOT_SIGNED = 'not_signed';

	public static function fromSignRequestStatus(SignRequestStatus $status): self {
		return match ($status) {
			SignRequestStatus::DRAFT => self::DRAFT,
			SignRequestStatus::ABLE_TO_SIGN => self::READY_TO_SIGN,
			SignRequestStatus::SIGNED => self::SIGNED,
			SignRequestStatus::REJECTED => self::REJECTED,
			SignRequestStatus::OBSERVING => self::OBSERVING,
		};
	}

	public function getLabel(IL10N $l10n): string {
		return match ($this) {
			self::DRAFT => SignRequestStatus::DRAFT->getLabel($l10n),
			self::READY_TO_SIGN => SignRequestStatus::ABLE_TO_SIGN->getLabel($l10n),
			self::SIGNED => SignRequestStatus::SIGNED->getLabel($l10n),
			self::REJECTED => SignRequestStatus::REJECTED->getLabel($l10n),
			self::OBSERVING => SignRequestStatus::OBSERVING->getLabel($l10n),
			// TRANSLATORS Neutral signer status shown when the viewer may know only that this signer has not signed, without the reason or whether they still can.
			self::NOT_SIGNED => $l10n->t('Not signed'),
		};
	}
}
