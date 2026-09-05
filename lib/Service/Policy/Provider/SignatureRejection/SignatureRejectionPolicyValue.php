<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignatureRejection;

use OCA\Libresign\Enum\SignatureRejectionCommentMode;

/**
 * @psalm-type SignatureRejectionPolicyShape = array{
 *     enabled: bool,
 *     comment_mode: string,
 *     allow_private_comment: bool,
 *     cancel_workflow: bool,
 *     public_status: bool,
 *     show_comment_on_validation: bool,
 * }
 */
final class SignatureRejectionPolicyValue {
	/**
	 * Signature rejection is opt-in: with the defaults below LibreSign keeps the
	 * behavior it had before the rejection workflow existed.
	 *
	 * @return SignatureRejectionPolicyShape
	 */
	public static function defaults(): array {
		return [
			'enabled' => false,
			'comment_mode' => SignatureRejectionCommentMode::DISABLED->value,
			'allow_private_comment' => false,
			'cancel_workflow' => false,
			'public_status' => false,
			'show_comment_on_validation' => false,
		];
	}

	/**
	 * @return SignatureRejectionPolicyShape
	 */
	public static function normalize(mixed $rawValue): array {
		if (is_string($rawValue)) {
			$decoded = json_decode($rawValue, true);
			if (is_array($decoded)) {
				$rawValue = $decoded;
			}
		}

		if (!is_array($rawValue)) {
			return self::defaults();
		}

		// Sub-options are meaningless while the feature is off; collapsing them here
		// keeps stored values, resolved values and snapshots consistent.
		if (!self::toBool($rawValue['enabled'] ?? false)) {
			return self::defaults();
		}

		$commentMode = SignatureRejectionCommentMode::tryFrom((string)($rawValue['comment_mode'] ?? ''))
			?? SignatureRejectionCommentMode::DISABLED;
		$commentsAllowed = $commentMode !== SignatureRejectionCommentMode::DISABLED;

		return [
			'enabled' => true,
			'comment_mode' => $commentMode->value,
			'allow_private_comment' => $commentsAllowed && self::toBool($rawValue['allow_private_comment'] ?? false),
			'cancel_workflow' => self::toBool($rawValue['cancel_workflow'] ?? false),
			'public_status' => self::toBool($rawValue['public_status'] ?? false),
			'show_comment_on_validation' => $commentsAllowed && self::toBool($rawValue['show_comment_on_validation'] ?? false),
		];
	}

	public static function isEnabled(mixed $rawValue): bool {
		return self::normalize($rawValue)['enabled'];
	}

	public static function getCommentMode(mixed $rawValue): SignatureRejectionCommentMode {
		return SignatureRejectionCommentMode::from(self::normalize($rawValue)['comment_mode']);
	}

	private static function toBool(mixed $rawValue): bool {
		if (is_bool($rawValue)) {
			return $rawValue;
		}

		if (is_string($rawValue)) {
			return in_array(strtolower(trim($rawValue)), ['1', 'true', 'yes', 'on'], true);
		}

		if (is_int($rawValue) || is_float($rawValue)) {
			return $rawValue > 0;
		}

		return false;
	}
}
