<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignatureRejection;

use OCA\Libresign\Enum\SignatureRejectionCommentMode;
use OCP\IL10N;

/**
 * Enforces the rules that no single rejection setting can express on its own.
 *
 * Each of the five settings is validated by its own policy key, which is enough
 * to know that a value exists and is allowed, but not to know that the settings
 * make sense together: a comment audience wider than the rejection audience
 * would let the comment disclose a rejection that must stay hidden, and a
 * dependent setting means nothing while the setting it depends on is off.
 *
 * The validator therefore always receives the final combined configuration,
 * never a single key, so the outcome does not depend on the order in which the
 * keys were saved. It is used on both writing paths: when an administrator
 * saves a policy layer and when a signature request is configured.
 *
 * Values that were merely inherited are corrected rather than refused: an
 * administrator lowering the rejection audience must not break every request
 * that already resolved a wider comment audience. Only what the caller is
 * submitting right now is refused, so a client never gets a silent result other
 * than the one it asked for.
 *
 * A single key written on its own therefore carries no cross-setting rule: the
 * caller is not stating what the other settings should be, and refusing it
 * would make the outcome depend on which key was saved first. What a stored
 * combination cannot do is disclose more than intended, and that is guaranteed
 * elsewhere: {@see SignatureRejectionPolicyConfig::fromValues()} narrows the
 * comment audience to the rejection audience whenever the configuration is
 * built.
 */
class SignatureRejectionPolicyValidator {
	public function __construct(
		private readonly ?IL10N $l10n = null,
	) {
	}

	/**
	 * A signature request is configured as a whole, so every dependency applies:
	 * a setting that cannot take effect on this document is refused rather than
	 * silently dropped.
	 *
	 * @param array<string, mixed> $combinedValues The five values, keyed by policy key
	 * @param list<string> $submittedKeys The keys the caller is writing right now
	 * @throws \InvalidArgumentException when the submitted combination breaks a dependency
	 */
	public function validateRequest(array $combinedValues, array $submittedKeys = []): SignatureRejectionPolicyConfig {
		return $this->validate($combinedValues, $submittedKeys, true);
	}

	/**
	 * A policy layer describes a standing rule rather than a document, so an
	 * administrator may configure the comment mode before enabling rejection,
	 * in whichever order they like. What cannot be allowed in any order is
	 * disclosing the comment to an audience that may not see the rejection, and
	 * that is decided on the configuration the write produces as a whole.
	 *
	 * @param array<string, mixed> $combinedValues The five values, keyed by policy key
	 * @param list<string> $submittedKeys The keys the administrator is writing right now
	 * @throws \InvalidArgumentException when the resulting combination would widen a disclosure
	 */
	public function validateLayer(array $combinedValues, array $submittedKeys): void {
		$this->validate($combinedValues, $submittedKeys, false);
	}

	/**
	 * @param array<string, mixed> $combinedValues
	 * @param list<string> $submittedKeys
	 */
	private function validate(array $combinedValues, array $submittedKeys, bool $dependentSettingsApply): SignatureRejectionPolicyConfig {
		$enabled = SignatureRejectionPolicyConfig::normalizeEnabled(
			$combinedValues[SignatureRejectionPolicy::KEY_ENABLED] ?? null,
		);
		$commentMode = SignatureRejectionPolicyConfig::normalizeCommentMode(
			$combinedValues[SignatureRejectionPolicy::KEY_COMMENT_MODE] ?? null,
		);
		$visibility = SignatureRejectionPolicyConfig::normalizeVisibility(
			$combinedValues[SignatureRejectionPolicy::KEY_VISIBILITY] ?? null,
			SignatureRejectionPolicyConfig::DEFAULT_VISIBILITY,
		);
		$commentVisibility = SignatureRejectionPolicyConfig::normalizeVisibility(
			$combinedValues[SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY] ?? null,
			SignatureRejectionPolicyConfig::DEFAULT_COMMENT_VISIBILITY,
		);

		if ($enabled && $commentMode !== SignatureRejectionCommentMode::DISABLED && !$visibility->covers($commentVisibility)) {
			$this->assertCommentAudienceIsNotWider($submittedKeys);
		} elseif ($dependentSettingsApply && !$enabled) {
			$this->assertNoDependentSettingSubmitted($combinedValues, $submittedKeys);
		} elseif ($dependentSettingsApply && $commentMode === SignatureRejectionCommentMode::DISABLED) {
			$this->assertCommentAudienceNotSubmitted($combinedValues, $submittedKeys);
		}

		return SignatureRejectionPolicyConfig::fromValues(
			$enabled,
			$combinedValues[SignatureRejectionPolicy::KEY_BEHAVIOR] ?? null,
			$commentMode,
			$visibility,
			$commentVisibility,
		);
	}

	/**
	 * @param array<string, mixed> $combinedValues
	 * @param list<string> $submittedKeys
	 */
	private function assertNoDependentSettingSubmitted(array $combinedValues, array $submittedKeys): void {
		foreach (SignatureRejectionPolicy::DEPENDENT_KEYS as $dependentKey) {
			if (!in_array($dependentKey, $submittedKeys, true)) {
				continue;
			}

			if ($this->isDefaultValue($dependentKey, $combinedValues[$dependentKey] ?? null)) {
				continue;
			}

			throw new \InvalidArgumentException($this->translate(
				// TRANSLATORS Error shown when a rejection setting is configured while signature rejection itself is turned off.
				'Signature rejection is disabled, so the other rejection settings cannot be configured.',
			));
		}
	}

	/**
	 * @param array<string, mixed> $combinedValues
	 * @param list<string> $submittedKeys
	 */
	private function assertCommentAudienceNotSubmitted(array $combinedValues, array $submittedKeys): void {
		if (!in_array(SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY, $submittedKeys, true)) {
			return;
		}

		if ($this->isDefaultValue(
			SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY,
			$combinedValues[SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY] ?? null,
		)) {
			return;
		}

		throw new \InvalidArgumentException($this->translate(
			// TRANSLATORS Error shown when the audience of a rejection comment is configured while rejection comments are turned off.
			'Rejection comments are disabled, so the audience of the comment cannot be configured.',
		));
	}

	/** @param list<string> $submittedKeys */
	private function assertCommentAudienceIsNotWider(array $submittedKeys): void {
		$submitsAudience = in_array(SignatureRejectionPolicy::KEY_VISIBILITY, $submittedKeys, true)
			|| in_array(SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY, $submittedKeys, true);
		if (!$submitsAudience) {
			return;
		}

		throw new \InvalidArgumentException($this->translate(
			// TRANSLATORS Error shown when the rejection comment would be visible to a wider audience than the rejection itself.
			'The rejection comment cannot be visible to a wider audience than the rejection itself.',
		));
	}

	private function isDefaultValue(string $policyKey, mixed $value): bool {
		$defaults = SignatureRejectionPolicyConfig::defaults()->toKeyedValues();

		return match ($policyKey) {
			SignatureRejectionPolicy::KEY_BEHAVIOR
				=> SignatureRejectionPolicyConfig::normalizeBehavior($value)->value === $defaults[$policyKey],
			SignatureRejectionPolicy::KEY_COMMENT_MODE
				=> SignatureRejectionPolicyConfig::normalizeCommentMode($value)->value === $defaults[$policyKey],
			SignatureRejectionPolicy::KEY_VISIBILITY, SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY
				=> SignatureRejectionPolicyConfig::normalizeVisibility(
					$value,
					SignatureRejectionPolicyConfig::DEFAULT_VISIBILITY,
				)->value === $defaults[$policyKey],
			default => SignatureRejectionPolicyConfig::normalizeEnabled($value) === $defaults[$policyKey],
		};
	}

	private function translate(string $message): string {
		return $this->l10n?->t($message) ?? $message;
	}
}
