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
	 * Whether a comment is private is deliberately not part of this shape. A
	 * comment can carry personal or legal information, so the choice belongs to
	 * the signer who writes it, never to an administrator.
	 *
	 * @return SignatureRejectionPolicyShape
	 */
	public static function defaults(): array {
		return [
			'enabled' => false,
			'comment_mode' => SignatureRejectionCommentMode::DISABLED->value,
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
			'cancel_workflow' => self::toBool($rawValue['cancel_workflow'] ?? false),
			'public_status' => self::toBool($rawValue['public_status'] ?? false),
			'show_comment_on_validation' => $commentsAllowed && self::toBool($rawValue['show_comment_on_validation'] ?? false),
		];
	}

	/**
	 * Build the value a requester asked for: they only choose whether rejection is
	 * offered on their document, every other rule stays the one the administrator set.
	 *
	 * @param array<string, mixed> $administrativeValue
	 * @return SignatureRejectionPolicyShape
	 */
	public static function withEnabled(array $administrativeValue, bool $enabled): array {
		return self::normalize(array_merge($administrativeValue, ['enabled' => $enabled]));
	}

	/**
	 * A requester may switch rejection off for a single document, but may never
	 * enable it where the administrative layers disabled it, nor relax the rules
	 * those layers defined.
	 */
	public static function isRequestOverrideAllowed(mixed $proposed, mixed $administrative): bool {
		$proposedValue = self::normalize($proposed);
		if (!$proposedValue['enabled']) {
			return true;
		}

		$administrativeValue = self::normalize($administrative);
		if (!$administrativeValue['enabled']) {
			return false;
		}

		return $proposedValue === $administrativeValue;
	}

	/**
	 * Read the requester choice sent with a signature request. Accepts both the
	 * bare boolean and the full policy shape, and returns null when the requester
	 * did not express a choice.
	 */
	public static function readRequestedChoice(mixed $rawValue): ?bool {
		if (is_bool($rawValue)) {
			return $rawValue;
		}

		if (is_string($rawValue)) {
			$trimmed = trim($rawValue);
			if ($trimmed === '') {
				return null;
			}

			$decoded = json_decode($trimmed, true);
			if (is_bool($decoded) || is_array($decoded)) {
				return self::readRequestedChoice($decoded);
			}

			return self::toBool($trimmed);
		}

		if (is_array($rawValue) && array_key_exists('enabled', $rawValue)) {
			return self::toBool($rawValue['enabled']);
		}

		return null;
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
