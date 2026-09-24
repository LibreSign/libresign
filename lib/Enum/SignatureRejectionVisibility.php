<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

/**
 * The widest audience that may learn about a rejection.
 *
 * The levels are cumulative: every audience also contains the ones below it,
 * so `public` includes the participants and the requester. The requester and
 * the signer who rejected always see their own record, whatever the level is.
 */
enum SignatureRejectionVisibility: string {
	/** Only the requester, plus the rejecting signer on their own entry. */
	case REQUESTER = 'requester';
	/** Also the signers taking part in the workflow. */
	case PARTICIPANTS = 'participants';
	/** Also the public validation responses. */
	case PUBLIC = 'public';

	/**
	 * Position in the hierarchy. Only meaningful when comparing two levels: the
	 * numbers themselves are not stored anywhere.
	 */
	public function level(): int {
		return match ($this) {
			self::REQUESTER => 0,
			self::PARTICIPANTS => 1,
			self::PUBLIC => 2,
		};
	}

	/** True when this level reaches at least as far as $other. */
	public function covers(self $other): bool {
		return $this->level() >= $other->level();
	}

	/** The narrower of the two levels. */
	public function narrowest(self $other): self {
		return $this->covers($other) ? $other : $this;
	}

	public static function tryFromValue(mixed $rawValue, self $fallback): self {
		if ($rawValue instanceof self) {
			return $rawValue;
		}

		return is_string($rawValue)
			? self::tryFrom(trim($rawValue)) ?? $fallback
			: $fallback;
	}
}
