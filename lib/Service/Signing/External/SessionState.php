<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\External;

/**
 * Persistent, deliberately non-retryable external private-key operation.
 *
 * Once SIGNING_PENDING has been acquired, losing the PHP worker or timing out
 * NEVER returns the session to READY_TO_SIGN. In particular, an expired lease
 * is not evidence that the private-key operation was not performed.
 */
enum SessionState: string {
	case PREPARING = 'preparing';
	case INTERACTION_REQUIRED = 'interaction_required';
	case AUTHORIZATION_PENDING = 'authorization_pending';
	case READY_TO_SIGN = 'ready_to_sign';
	case SIGNING_PENDING = 'signing_pending';
	case SIGNATURE_RECEIVED = 'signature_received';
	case COMPLETING = 'completing';
	case COMPLETED = 'completed';
	case REJECTED = 'rejected';
	case EXPIRED = 'expired';
	case FAILED = 'failed';
	case UNCERTAIN = 'uncertain';

	/** @return list<self> */
	public function next(): array {
		return match ($this) {
			self::PREPARING => [self::INTERACTION_REQUIRED, self::AUTHORIZATION_PENDING, self::READY_TO_SIGN, self::REJECTED, self::FAILED, self::EXPIRED],
			self::INTERACTION_REQUIRED => [self::AUTHORIZATION_PENDING, self::READY_TO_SIGN, self::REJECTED, self::FAILED, self::EXPIRED],
			self::AUTHORIZATION_PENDING => [self::INTERACTION_REQUIRED, self::READY_TO_SIGN, self::REJECTED, self::FAILED, self::EXPIRED],
			self::READY_TO_SIGN => [self::SIGNING_PENDING, self::REJECTED, self::FAILED, self::EXPIRED],
			self::SIGNING_PENDING => [self::SIGNATURE_RECEIVED, self::UNCERTAIN, self::REJECTED, self::FAILED],
			self::UNCERTAIN => [self::SIGNATURE_RECEIVED, self::REJECTED, self::FAILED],
			self::SIGNATURE_RECEIVED => [self::COMPLETING, self::EXPIRED, self::FAILED],
			self::COMPLETING => [self::COMPLETED, self::FAILED],
			self::COMPLETED, self::REJECTED, self::EXPIRED, self::FAILED => [],
		};
	}

	public function allows(self $next): bool {
		return in_array($next, $this->next(), true);
	}
}
