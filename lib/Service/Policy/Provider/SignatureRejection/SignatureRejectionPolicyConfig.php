<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignatureRejection;

use OCA\Libresign\Enum\SignatureRejectionBehavior;
use OCA\Libresign\Enum\SignatureRejectionCommentMode;
use OCA\Libresign\Enum\SignatureRejectionVisibility;

/**
 * The five rejection settings read as one configuration.
 *
 * Each setting is its own policy key, so an administrator can allow or enforce
 * them one by one. They only describe a workflow when read together, which is
 * what this object is for: it is built from the five resolved values and is the
 * shape the signing flow, the snapshot and the cross-setting validator work
 * with.
 *
 * Building one always collapses settings that cannot apply: nothing is
 * configurable while rejection is disabled, the comment audience is meaningless
 * while comments are disabled, and the comment can never reach further than the
 * rejection itself. Collapsing here rather than at each reader keeps stored
 * values, resolved values and snapshots consistent, and keeps a stale or
 * hand-written layer from disclosing more than the rules allow.
 *
 * @psalm-type SignatureRejectionPolicyShape = array{
 *     rejection_enabled: bool,
 *     rejection_behavior: 'cancel'|'continue',
 *     rejection_comment_mode: 'disabled'|'optional'|'required',
 *     rejection_visibility: 'requester'|'participants'|'public',
 *     rejection_comment_visibility: 'requester'|'participants'|'public',
 * }
 */
final class SignatureRejectionPolicyConfig {
	/**
	 * Signature rejection is opt-in: with the defaults below LibreSign keeps the
	 * behavior it had before the rejection workflow existed.
	 *
	 * Whether a comment is private is deliberately not part of this shape. A
	 * comment can carry personal or legal information, so the choice belongs to
	 * the signer who writes it, never to an administrator.
	 */
	public const DEFAULT_ENABLED = false;
	public const DEFAULT_BEHAVIOR = SignatureRejectionBehavior::CANCEL;
	public const DEFAULT_COMMENT_MODE = SignatureRejectionCommentMode::DISABLED;
	public const DEFAULT_VISIBILITY = SignatureRejectionVisibility::REQUESTER;
	public const DEFAULT_COMMENT_VISIBILITY = SignatureRejectionVisibility::REQUESTER;

	private function __construct(
		private readonly bool $enabled,
		private readonly SignatureRejectionBehavior $behavior,
		private readonly SignatureRejectionCommentMode $commentMode,
		private readonly SignatureRejectionVisibility $visibility,
		private readonly SignatureRejectionVisibility $commentVisibility,
	) {
	}

	public static function defaults(): self {
		return new self(
			self::DEFAULT_ENABLED,
			self::DEFAULT_BEHAVIOR,
			self::DEFAULT_COMMENT_MODE,
			self::DEFAULT_VISIBILITY,
			self::DEFAULT_COMMENT_VISIBILITY,
		);
	}

	/**
	 * Build the configuration from the five raw values, in whatever shape the
	 * policy layers, a request payload or a snapshot stored them.
	 */
	public static function fromValues(
		mixed $enabled = null,
		mixed $behavior = null,
		mixed $commentMode = null,
		mixed $visibility = null,
		mixed $commentVisibility = null,
	): self {
		if (!self::normalizeEnabled($enabled)) {
			return self::defaults();
		}

		$normalizedCommentMode = self::normalizeCommentMode($commentMode);
		$normalizedVisibility = self::normalizeVisibility($visibility, self::DEFAULT_VISIBILITY);
		$normalizedCommentVisibility = $normalizedCommentMode === SignatureRejectionCommentMode::DISABLED
			? self::DEFAULT_COMMENT_VISIBILITY
			: self::normalizeVisibility($commentVisibility, self::DEFAULT_COMMENT_VISIBILITY)->narrowest($normalizedVisibility);

		return new self(
			true,
			self::normalizeBehavior($behavior),
			$normalizedCommentMode,
			$normalizedVisibility,
			$normalizedCommentVisibility,
		);
	}

	/**
	 * Build the configuration from a map keyed by policy key, such as the values
	 * resolved for a user or the entries frozen in a policy snapshot. Missing
	 * keys fall back to the system default of that setting.
	 *
	 * @param array<string, mixed> $values
	 */
	public static function fromKeyedValues(array $values): self {
		return self::fromValues(
			$values[SignatureRejectionPolicy::KEY_ENABLED] ?? null,
			$values[SignatureRejectionPolicy::KEY_BEHAVIOR] ?? null,
			$values[SignatureRejectionPolicy::KEY_COMMENT_MODE] ?? null,
			$values[SignatureRejectionPolicy::KEY_VISIBILITY] ?? null,
			$values[SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY] ?? null,
		);
	}

	/** @return SignatureRejectionPolicyShape */
	public function toKeyedValues(): array {
		return [
			SignatureRejectionPolicy::KEY_ENABLED => $this->enabled,
			SignatureRejectionPolicy::KEY_BEHAVIOR => $this->behavior->value,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => $this->commentMode->value,
			SignatureRejectionPolicy::KEY_VISIBILITY => $this->visibility->value,
			SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => $this->commentVisibility->value,
		];
	}

	public function isEnabled(): bool {
		return $this->enabled;
	}

	public function getBehavior(): SignatureRejectionBehavior {
		return $this->behavior;
	}

	public function cancelsWorkflow(): bool {
		return $this->enabled && $this->behavior === SignatureRejectionBehavior::CANCEL;
	}

	public function getCommentMode(): SignatureRejectionCommentMode {
		return $this->commentMode;
	}

	public function allowsComment(): bool {
		return $this->commentMode !== SignatureRejectionCommentMode::DISABLED;
	}

	public function getVisibility(): SignatureRejectionVisibility {
		return $this->visibility;
	}

	public function getCommentVisibility(): SignatureRejectionVisibility {
		return $this->commentVisibility;
	}

	/** True when the audience below may learn that the signer rejected. */
	public function isRejectionVisibleTo(SignatureRejectionVisibility $audience): bool {
		return $this->enabled && $this->visibility->covers($audience);
	}

	/** True when the audience below may read the words the signer wrote. */
	public function isCommentVisibleTo(SignatureRejectionVisibility $audience): bool {
		return $this->enabled && $this->allowsComment() && $this->commentVisibility->covers($audience);
	}

	public static function normalizeEnabled(mixed $rawValue): bool {
		if (is_bool($rawValue)) {
			return $rawValue;
		}

		if (is_string($rawValue)) {
			return in_array(strtolower(trim($rawValue)), ['1', 'true', 'yes', 'on'], true);
		}

		if (is_int($rawValue) || is_float($rawValue)) {
			return $rawValue > 0;
		}

		return self::DEFAULT_ENABLED;
	}

	public static function normalizeBehavior(mixed $rawValue): SignatureRejectionBehavior {
		if ($rawValue instanceof SignatureRejectionBehavior) {
			return $rawValue;
		}

		return is_string($rawValue)
			? SignatureRejectionBehavior::tryFrom(trim($rawValue)) ?? self::DEFAULT_BEHAVIOR
			: self::DEFAULT_BEHAVIOR;
	}

	public static function normalizeCommentMode(mixed $rawValue): SignatureRejectionCommentMode {
		if ($rawValue instanceof SignatureRejectionCommentMode) {
			return $rawValue;
		}

		return is_string($rawValue)
			? SignatureRejectionCommentMode::tryFrom(trim($rawValue)) ?? self::DEFAULT_COMMENT_MODE
			: self::DEFAULT_COMMENT_MODE;
	}

	public static function normalizeVisibility(mixed $rawValue, SignatureRejectionVisibility $fallback): SignatureRejectionVisibility {
		return SignatureRejectionVisibility::tryFromValue($rawValue, $fallback);
	}

	/**
	 * Normalize a raw value the way the policy key that owns it does, so a value
	 * read back from a request, a layer or a snapshot can be compared with a
	 * resolved one.
	 */
	public static function normalizeKeyedValue(string $policyKey, mixed $rawValue): bool|string {
		return match ($policyKey) {
			SignatureRejectionPolicy::KEY_BEHAVIOR => self::normalizeBehavior($rawValue)->value,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => self::normalizeCommentMode($rawValue)->value,
			SignatureRejectionPolicy::KEY_VISIBILITY => self::normalizeVisibility($rawValue, self::DEFAULT_VISIBILITY)->value,
			SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => self::normalizeVisibility($rawValue, self::DEFAULT_COMMENT_VISIBILITY)->value,
			default => self::normalizeEnabled($rawValue),
		};
	}
}
